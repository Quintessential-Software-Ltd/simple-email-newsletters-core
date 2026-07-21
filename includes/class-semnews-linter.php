<?php
/**
 * Honest subject-line & content linter.
 *
 * Advisory only — it NEVER blocks a send. It nudges the sender away from the
 * dishonest tricks that erode trust (fake RE:/FW: prefixes, shouting in all
 * caps, image-only emails with no real text, a missing preheader). Warnings are
 * shown in the composer (and the Pro add-on's automation screen); the sender decides.
 *
 * @package QuintessentialNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Linter.
 */
class SEMNEWS_Linter {

	const SEVERITY_WARN = 'warn';
	const SEVERITY_INFO = 'info';

	/**
	 * Lint a subject line.
	 *
	 * @param string $subject Subject.
	 * @return array[] List of { code, severity, message }.
	 */
	public static function lint_subject( $subject ) {
		$subject = trim( (string) $subject );
		$issues  = array();

		if ( '' === $subject ) {
			$issues[] = self::issue( 'empty_subject', self::SEVERITY_WARN, __( 'The subject line is empty. A clear, honest subject helps people decide to open — and is required before you can send.', 'quintessential-newsletters' ) );
			return $issues; // Nothing else to check.
		}

		// Fake reply / forward prefixes.
		if ( preg_match( '/^\s*(re|fw|fwd)\s*:/i', $subject ) ) {
			$issues[] = self::issue( 'fake_reply', self::SEVERITY_WARN, __( 'Avoid starting with “Re:” or “Fwd:” unless this really is a reply. Fake reply prefixes feel deceptive and can hurt deliverability.', 'quintessential-newsletters' ) );
		}

		// ALL CAPS (consider only letters; ignore short subjects).
		$letters = preg_replace( '/[^a-z]/i', '', $subject );
		if ( strlen( $letters ) >= 8 && $letters === strtoupper( $letters ) && preg_match( '/[A-Z]/', $letters ) ) {
			$issues[] = self::issue( 'all_caps', self::SEVERITY_WARN, __( 'The subject is in ALL CAPS. It reads as shouting and trips spam filters — sentence case performs better.', 'quintessential-newsletters' ) );
		}

		// Excessive exclamation / punctuation.
		if ( preg_match( '/!{2,}/', $subject ) || substr_count( $subject, '!' ) > 1 ) {
			$issues[] = self::issue( 'punctuation', self::SEVERITY_WARN, __( 'Multiple exclamation marks look spammy. One honest sentence beats hype.', 'quintessential-newsletters' ) );
		}

		// Length: most inboxes truncate around 60 characters.
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $subject ) > 60 : strlen( $subject ) > 60 ) {
			$issues[] = self::issue( 'too_long', self::SEVERITY_INFO, __( 'The subject is over 60 characters and may be truncated in many inboxes. Front-load the important words.', 'quintessential-newsletters' ) );
		}

		/**
		 * Filter the subject-line lint issues.
		 *
		 * @param array  $issues  Issues.
		 * @param string $subject Subject.
		 */
		return apply_filters( 'semnews_lint_subject', $issues, $subject );
	}

	/**
	 * Lint a whole newsletter (subject + preheader + body).
	 *
	 * @param string $subject   Subject.
	 * @param string $body      HTML body.
	 * @param string $preheader Preheader text.
	 * @return array[] List of { code, severity, message }.
	 */
	public static function lint_campaign( $subject, $body, $preheader = '' ) {
		$issues = self::lint_subject( $subject );

		// Missing preheader.
		if ( '' === trim( (string) $preheader ) ) {
			$issues[] = self::issue( 'no_preheader', self::SEVERITY_WARN, __( 'No preheader set. The preheader is the preview text after the subject — without it, inboxes show a random snippet.', 'quintessential-newsletters' ) );
		}

		// Image-only email: has <img> but very little real text.
		$body      = (string) $body;
		$has_image = (bool) preg_match( '/<img\b/i', $body );
		$text      = trim( wp_strip_all_tags( $body ) );
		$text_len  = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );

		if ( $has_image && $text_len < 25 ) {
			$issues[] = self::issue( 'image_only', self::SEVERITY_WARN, __( 'This looks like an image-only email. Many clients block images by default and screen readers can’t read them — always include real text.', 'quintessential-newsletters' ) );
		}

		if ( '' !== trim( $body ) && $text_len < 1 ) {
			$issues[] = self::issue( 'no_text', self::SEVERITY_WARN, __( 'There is no readable text in the body. Add some words so the message works with images off.', 'quintessential-newsletters' ) );
		}

		/**
		 * Filter the campaign lint issues.
		 *
		 * @param array  $issues  Issues.
		 * @param string $subject Subject.
		 * @param string $body    Body.
		 */
		return apply_filters( 'semnews_lint_campaign', $issues, $subject, $body );
	}

	/**
	 * Build an issue array.
	 *
	 * @param string $code     Code.
	 * @param string $severity warn|info.
	 * @param string $message  Human message.
	 * @return array
	 */
	protected static function issue( $code, $severity, $message ) {
		return array(
			'code'     => $code,
			'severity' => $severity,
			'message'  => $message,
		);
	}

	/**
	 * Render a list of issues as an admin-friendly HTML block (escaped).
	 *
	 * @param array $issues Issues.
	 * @return string
	 */
	public static function render_notice_html( $issues ) {
		if ( empty( $issues ) ) {
			return '<p class="semnews-lint-ok">' . esc_html__( '✓ Looks good. No subject-line concerns.', 'quintessential-newsletters' ) . '</p>';
		}

		$out = '<ul class="semnews-lint-list">';
		foreach ( $issues as $issue ) {
			$cls  = self::SEVERITY_INFO === $issue['severity'] ? 'is-info' : 'is-warn';
			$icon = self::SEVERITY_INFO === $issue['severity'] ? 'ℹ' : '!';
			$out .= '<li class="' . esc_attr( $cls ) . '"><span class="semnews-lint-icon" aria-hidden="true">' . esc_html( $icon ) . '</span> ' . esc_html( $issue['message'] ) . '</li>';
		}
		$out .= '</ul>';

		return $out;
	}
}
