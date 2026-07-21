<?php
/**
 * Email building and sending.
 *
 * Every email this plugin sends includes an honest footer (who is sending,
 * a postal address, and a working unsubscribe link) and the RFC 8058
 * List-Unsubscribe / List-Unsubscribe-Post headers so that mailbox providers
 * can offer one-click unsubscribe.
 *
 * @package SimpleEmailNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mailer.
 */
class SEMNEWS_Mailer {

	/**
	 * From / Reply-To / List headers shared by every message.
	 *
	 * @param object|null $subscriber Subscriber for unsubscribe headers (optional).
	 * @return array Headers for wp_mail().
	 */
	protected static function base_headers( $subscriber = null, $sender = null ) {
		if ( is_array( $sender ) ) {
			// A named sender profile overrides the global From/Reply-To.
			$from_name  = isset( $sender['from_name'] ) ? $sender['from_name'] : '';
			$from_email = isset( $sender['from_email'] ) ? $sender['from_email'] : '';
			$reply_to   = ! empty( $sender['reply_to'] ) ? $sender['reply_to'] : '';
		} else {
			$from_name  = semnews_get_option( 'from_name' );
			$from_email = semnews_get_option( 'from_email' );
			$reply_to   = semnews_get_option( 'reply_to' );
		}

		$headers   = array();
		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		// Sanitise to avoid header injection (sanitize_email/text strips CR/LF).
		$from_email = sanitize_email( $from_email );
		$from_name  = self::sanitize_header( $from_name );

		if ( $from_email ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );
		}
		if ( $reply_to && sanitize_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( $reply_to );
		}

		if ( $subscriber ) {
			$unsub_url  = semnews_action_url( 'unsubscribe', $subscriber->token, $subscriber->id );
			$mailto     = $from_email ? 'mailto:' . $from_email . '?subject=unsubscribe' : '';
			$list_parts = array( '<' . esc_url_raw( $unsub_url ) . '>' );
			if ( $mailto ) {
				$list_parts[] = '<' . $mailto . '>';
			}
			$headers[] = 'List-Unsubscribe: ' . implode( ', ', $list_parts );
			// One-click unsubscribe per RFC 8058.
			$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}

		return apply_filters( 'semnews_mail_headers', $headers, $subscriber );
	}

	/**
	 * Strip characters that could be used for email header injection.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected static function sanitize_header( $value ) {
		return trim( preg_replace( '/[\r\n]+/', ' ', (string) $value ) );
	}

	/**
	 * Wrap body content in the shared HTML email layout (header + footer).
	 *
	 * @param string      $title      Visible/hidden title.
	 * @param string      $content    Inner HTML.
	 * @param object|null $subscriber Subscriber, for the unsubscribe link.
	 * @param string      $preheader  Optional preview text.
	 * @return string
	 */
	public static function wrap( $title, $content, $subscriber = null, $preheader = '' ) {
		$footer = self::footer_html( $subscriber );

		$html = semnews_render_template(
			'emails/layout.php',
			array(
				'title'     => $title,
				'content'   => $content,
				'footer'    => $footer,
				'preheader' => $preheader,
			)
		);

		if ( '' === $html ) {
			// Fallback if template missing.
			$html = '<!DOCTYPE html><html><body>' . $content . $footer . '</body></html>';
		}

		return $html;
	}

	/**
	 * Branded header for the transactional emails (confirmation + welcome).
	 *
	 * Shows the brand logo when one is supplied via the `semnews_email_logo_url`
	 * filter (the Pro add-on feeds its Settings logo through it), otherwise a
	 * simple text wordmark built from the company name. Recognisable branding
	 * at the top of these first emails is a genuine deliverability signal —
	 * recipients who recognise the sender do not press "report spam".
	 *
	 * @return string HTML, or '' when there is nothing to brand with.
	 */
	public static function transactional_header() {
		/**
		 * Filter the logo shown at the top of transactional emails.
		 *
		 * @param string $url Image URL ('' for none).
		 */
		$logo    = esc_url( apply_filters( 'semnews_email_logo_url', '' ) );
		$company = semnews_get_option( 'company_name' );
		$home    = home_url( '/' );

		if ( $logo ) {
			$alt   = $company ? $company : get_bloginfo( 'name' );
			$inner = sprintf(
				'<a href="%s" style="text-decoration:none;"><img src="%s" alt="%s" width="180" style="max-width:180px;height:auto;border:0;" /></a>',
				esc_url( $home ),
				$logo,
				esc_attr( $alt )
			);
		} elseif ( $company ) {
			$inner = sprintf(
				'<a href="%s" style="font-size:20px;font-weight:700;letter-spacing:0.3px;color:#1d2327;text-decoration:none;">%s</a>',
				esc_url( $home ),
				esc_html( $company )
			);
		} else {
			return '';
		}

		return '<div style="text-align:center;padding-bottom:20px;margin-bottom:28px;border-bottom:1px solid #eceef0;">' . $inner . '</div>';
	}

	/**
	 * The compliant footer: sender identity, postal address, unsubscribe link.
	 *
	 * @param object|null $subscriber Subscriber.
	 * @return string
	 */
	public static function footer_html( $subscriber = null ) {
		$company = semnews_get_option( 'company_name' );
		$address = semnews_get_option( 'postal_address' );

		$unsub = '';
		if ( $subscriber ) {
			$unsub_url = semnews_action_url( 'unsubscribe', $subscriber->token, $subscriber->id );
			$prefs_url = semnews_action_url( 'preferences', $subscriber->token, $subscriber->id );
			// The preference centre links onward to "see your data", so the raw PII
			// view is not one hop from a forwardable email link.
			$unsub     = sprintf(
				'<a href="%s">%s</a> &nbsp;|&nbsp; <a href="%s">%s</a>',
				esc_url( $unsub_url ),
				esc_html__( 'Unsubscribe', 'quintessential-newsletters' ),
				esc_url( $prefs_url ),
				esc_html__( 'Update your preferences', 'quintessential-newsletters' )
			);
		}

		ob_start();
		?>
		<div style="margin-top:24px;padding-top:16px;border-top:1px solid #e2e2e2;font-size:12px;color:#777;line-height:1.6;">
			<?php if ( $company ) : ?>
				<div><strong><?php echo esc_html( $company ); ?></strong></div>
			<?php endif; ?>
			<?php if ( $address ) : ?>
				<div><?php echo nl2br( esc_html( $address ) ); ?></div>
			<?php endif; ?>
			<?php if ( $unsub ) : ?>
				<div style="margin-top:8px;"><?php echo wp_kses_post( $unsub ); ?></div>
			<?php endif; ?>
			<?php if ( $subscriber ) : ?>
				<div style="margin-top:8px;">
					<?php
					/* translators: %s: subscriber email address. */
					printf( esc_html__( 'You are receiving this because %s subscribed to our newsletter.', 'quintessential-newsletters' ), esc_html( $subscriber->email ) );
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Convert an HTML email to a reasonable plain-text alternative.
	 *
	 * @param string $html HTML body.
	 * @return string
	 */
	public static function to_plain_text( $html ) {
		$text = preg_replace( '/<(head|style|script).*?<\/\1>/is', '', $html );
		// Drop the invisible preheader padding (a run of &nbsp;&zwnj; entities);
		// decoded, it would litter the text part with invisible characters. The
		// preheader text itself is kept — leading the text part with it means
		// clients that build the inbox snippet from text/plain show it too.
		$text = preg_replace( '/<div class="semnews-preheader-pad".*?<\/div>/is', '', $text );
		$text = preg_replace( '/<br\s*\/?>/i', "\n", $text );
		$text = preg_replace( '/<\/(p|div|tr|h[1-6]|li)>/i', "\n", $text );
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( "/[ \t]+/", ' ', $text );
		$text = preg_replace( "/\n\s*\n\s*\n+/", "\n\n", $text );
		return trim( $text );
	}

	/**
	 * Low-level send helper that also attaches a text/plain alternative.
	 *
	 * @param string      $to         Recipient email.
	 * @param string      $subject    Subject.
	 * @param string      $html       HTML body (already wrapped).
	 * @param object|null $subscriber Subscriber (for headers).
	 * @return bool
	 */
	public static function send( $to, $subject, $html, $subscriber = null, $sender = null ) {
		$to      = sanitize_email( $to );
		$subject = self::sanitize_header( $subject );
		$headers = self::base_headers( $subscriber, $sender );

		$plain = self::to_plain_text( $html );

		// Attach a plain-text part via PHPMailer for better deliverability,
		// and align the envelope sender (Return-Path) with the From address
		// so SPF is evaluated against the sender's domain instead of the web
		// host's default. Hosts that manage the envelope themselves can veto
		// this by returning '' from the filter.
		$attach_plain = function ( $phpmailer ) use ( $plain ) {
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$phpmailer->AltBody = $plain;

			/**
			 * Filter the envelope sender (Return-Path) address.
			 *
			 * @param string $envelope Proposed envelope sender (the From address).
			 */
			$envelope = apply_filters( 'semnews_envelope_sender', $phpmailer->From );
			if ( '' === (string) $phpmailer->Sender && $envelope && is_email( $envelope ) ) {
				$phpmailer->Sender = $envelope;
			}
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		};
		add_action( 'phpmailer_init', $attach_plain );

		$sent = wp_mail( $to, $subject, $html, $headers );

		remove_action( 'phpmailer_init', $attach_plain );

		return (bool) $sent;
	}

	/**
	 * Send the double opt-in confirmation email.
	 *
	 * @param object $subscriber Subscriber.
	 * @return bool
	 */
	public static function send_confirmation( $subscriber ) {
		$confirm_url = semnews_action_url( 'confirm', $subscriber->token, $subscriber->id );
		$company     = semnews_get_option( 'company_name' );

		$content = semnews_render_template(
			'emails/confirmation.php',
			array(
				'subscriber'   => $subscriber,
				'confirm_url'  => $confirm_url,
				'company'      => $company,
				'custom_intro' => (string) semnews_get_option( 'confirmation_intro', '' ),
				'header'       => self::transactional_header(),
				'site_url'     => home_url( '/' ),
			)
		);

		// Site owners can override the subject in Settings; {company} is replaced.
		$custom_subject = trim( (string) semnews_get_option( 'confirmation_subject', '' ) );
		if ( '' !== $custom_subject ) {
			$subject = strtr( $custom_subject, array( '{company}' => $company ) );
		} else {
			/* translators: %s: site / company name. */
			$subject = sprintf( __( 'Please confirm your subscription to %s', 'quintessential-newsletters' ), $company );
		}
		$subject = apply_filters( 'semnews_confirmation_subject', $subject, $subscriber );

		$html = self::wrap( $subject, $content, null );

		return self::send( $subscriber->email, $subject, $html, null );
	}

	/**
	 * Send the welcome email after confirmation.
	 *
	 * @param object $subscriber Subscriber.
	 * @return bool
	 */
	public static function send_welcome( $subscriber ) {
		$company = semnews_get_option( 'company_name' );

		$content = semnews_render_template(
			'emails/welcome.php',
			array(
				'subscriber'   => $subscriber,
				'company'      => $company,
				'custom_intro' => (string) semnews_get_option( 'welcome_intro', '' ),
				'header'       => self::transactional_header(),
				'site_url'     => home_url( '/' ),
				'site_name'    => get_bloginfo( 'name' ),
			)
		);

		$custom_subject = trim( (string) semnews_get_option( 'welcome_subject', '' ) );
		if ( '' !== $custom_subject ) {
			$subject = strtr( $custom_subject, array( '{company}' => $company ) );
		} else {
			/* translators: %s: site / company name. */
			$subject = sprintf( __( 'Welcome to %s', 'quintessential-newsletters' ), $company );
		}
		$subject = apply_filters( 'semnews_welcome_subject', $subject, $subscriber );

		$html = self::wrap( $subject, $content, $subscriber );

		return self::send( $subscriber->email, $subject, $html, $subscriber );
	}

	/**
	 * Send a campaign to one subscriber.
	 *
	 * @param object $campaign   Campaign row.
	 * @param object $subscriber Subscriber row.
	 * @return bool
	 */
	public static function send_campaign_to( $campaign, $subscriber ) {
		$sender = self::campaign_sender( $campaign );

		$body = self::personalize( $campaign->body, $subscriber );

		/**
		 * Filter the personalised campaign body for one subscriber, before
		 * the shared layout (header + footer/unsubscribe) is wrapped around
		 * it — so footer links can never be altered here. Used by the Pro
		 * add-on's consent-aware click tracking to rewrite content links.
		 *
		 * @param string $body       Personalised inner HTML.
		 * @param object $campaign   Campaign row (id 0 for test/welcome sends).
		 * @param object $subscriber Subscriber row.
		 */
		$body = apply_filters( 'semnews_campaign_body_for_subscriber', $body, $campaign, $subscriber );

		$html = self::wrap( $campaign->subject, $body, $subscriber, self::personalize( $campaign->preheader, $subscriber ) );

		$subject = self::personalize( $campaign->subject, $subscriber );

		return self::send( $subscriber->email, $subject, $html, $subscriber, $sender );
	}

	/**
	 * Replace simple, honest merge tags. No fake personalization tricks.
	 *
	 * Supported: {{name}}, {{email}}, {{first_name}}.
	 *
	 * @param string $text       Text containing tags.
	 * @param object $subscriber Subscriber.
	 * @return string
	 */
	public static function personalize( $text, $subscriber ) {
		$name       = $subscriber->name ? $subscriber->name : '';
		$first_name = $name ? explode( ' ', trim( $name ) )[0] : '';

		$replacements = array(
			'{{name}}'       => $name,
			'{{first_name}}' => $first_name,
			'{{email}}'      => $subscriber->email,
		);
		$replacements = apply_filters( 'semnews_merge_tags', $replacements, $subscriber );

		return strtr( $text, $replacements );
	}

	/**
	 * Resolve the sender profile for a campaign.
	 *
	 * Core always uses the global From/Reply-To from Settings (null). The Pro
	 * add-on hooks this filter to map the campaign's sender_id to one of its
	 * named sender profiles.
	 *
	 * @param object $campaign Campaign row.
	 * @return array|null Sender array { from_name, from_email, reply_to } or null.
	 */
	protected static function campaign_sender( $campaign ) {
		return apply_filters( 'semnews_campaign_sender', null, $campaign );
	}

	/**
	 * Send a test copy of a campaign to an arbitrary address.
	 *
	 * @param object $campaign Campaign row.
	 * @param string $to       Recipient.
	 * @return bool
	 */
	public static function send_test( $campaign, $to ) {
		$fake             = new stdClass();
		$fake->id         = 0;
		$fake->email      = $to;
		$fake->name       = __( 'Test Recipient', 'quintessential-newsletters' );
		$fake->token      = 'preview';
		$fake->status     = SEMNEWS_Subscribers::STATUS_SUBSCRIBED;

		$sender  = self::campaign_sender( $campaign );
		$body    = self::personalize( $campaign->body, $fake );
		$prefix  = '[' . __( 'TEST', 'quintessential-newsletters' ) . '] ';
		$subject = $prefix . self::personalize( $campaign->subject, $fake );
		$html    = self::wrap( $campaign->subject, $body, $fake, self::personalize( $campaign->preheader, $fake ) );

		return self::send( $to, $subject, $html, $fake, $sender );
	}
}
