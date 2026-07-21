<?php
/**
 * Shared helper functions.
 *
 * @package SimpleEmailNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default plugin settings.
 *
 * @return array
 */
function semnews_default_settings() {
	$admin_email = get_option( 'admin_email' );

	// The semnews_default_settings filter lets add-ons (e.g. Pro) register their own
	// keys so semnews_get_option() serves them with sensible defaults.
	return apply_filters( 'semnews_default_settings', array(
		'from_name'           => get_bloginfo( 'name' ),
		'from_email'          => $admin_email,
		'reply_to'            => $admin_email,
		'double_optin'        => 1,
		'send_welcome'        => 1,
		'company_name'        => get_bloginfo( 'name' ),
		'postal_address'      => '',
		'privacy_policy_page' => (int) get_option( 'wp_page_for_privacy_policy', 0 ),
		'consent_text'        => __( 'I agree to receive email newsletters and accept the privacy policy. You can unsubscribe at any time.', 'quintessential-newsletters' ),
		'success_message'     => __( 'Almost done! Please check your inbox and click the confirmation link to complete your subscription.', 'quintessential-newsletters' ),
		'success_message_single' => __( 'Thanks for subscribing!', 'quintessential-newsletters' ),
		'confirmation_subject' => '', // Empty = built-in default wording.
		'confirmation_intro'   => '',
		'welcome_subject'      => '',
		'welcome_intro'        => '',
		'batch_size'          => 50,
		'retention_days'      => 30, // Auto-purge unconfirmed signups after N days (GDPR data minimisation).
		'delete_data_on_uninstall' => 0, // Keep data by default; deleting people's data should be deliberate.
	) );
}

/**
 * Get a single plugin setting (merged with defaults).
 *
 * @param string $key     Setting key.
 * @param mixed  $default Fallback if not set.
 * @return mixed
 */
function semnews_get_option( $key, $default = null ) {
	$settings = get_option( 'semnews_settings', array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	$settings = wp_parse_args( $settings, semnews_default_settings() );

	if ( array_key_exists( $key, $settings ) ) {
		return $settings[ $key ];
	}

	return $default;
}

/**
 * Whether the Pro add-on is active. Used to hide the Upgrade page and the
 * greyed-out Pro previews once the real controls are available.
 *
 * @return bool
 */
function semnews_pro_active() {
	return defined( 'SEMNEWSP_VERSION' );
}

/**
 * Get the full settings array (merged with defaults).
 *
 * @return array
 */
function semnews_get_settings() {
	$settings = get_option( 'semnews_settings', array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	return wp_parse_args( $settings, semnews_default_settings() );
}

/**
 * Best-effort client IP, used only as proof-of-consent (GDPR Art. 7).
 *
 * We deliberately keep this conservative: we read REMOTE_ADDR and validate it.
 * Proxy headers are spoofable, so we only trust them when the site owner has
 * explicitly opted in via the `semnews_trust_proxy_headers` filter.
 *
 * @return string Validated IP address, or empty string.
 */
function semnews_get_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	if ( apply_filters( 'semnews_trust_proxy_headers', false ) ) {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ) as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$candidate = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// X-Forwarded-For may be a comma-separated list; take the first.
				$candidate = trim( explode( ',', $candidate )[0] );
				if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
					$ip = $candidate;
					break;
				}
			}
		}
	}

	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/**
 * Truncated user-agent string for the consent log.
 *
 * @return string
 */
function semnews_get_user_agent() {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	return substr( $ua, 0, 255 );
}

/**
 * Generate a cryptographically secure, URL-safe token for opt-in / unsubscribe links.
 *
 * @return string 32-character token.
 */
function semnews_generate_token() {
	return wp_generate_password( 32, false, false );
}

/**
 * Neutralise spreadsheet formula-injection leads for a CSV cell (CWE-1236).
 *
 * sanitize_text_field() strips HTML but not the characters a spreadsheet treats
 * as a formula start (= + - @, tab, CR). A subscriber-supplied name/source such
 * as `=HYPERLINK(...)` would otherwise execute when the owner opens the export.
 * Prefix any such value with an apostrophe so Excel / Sheets / LibreOffice render
 * it as plain text.
 *
 * @param mixed $value Cell value.
 * @return string
 */
function semnews_csv_field( $value ) {
	$value = (string) $value;
	if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
		$value = "'" . $value;
	}
	return $value;
}

/**
 * Build a public action URL (confirm / unsubscribe / preferences) for a subscriber.
 *
 * @param string $action  One of: confirm, unsubscribe, preferences.
 * @param string $token   Subscriber token.
 * @param int    $id      Subscriber ID.
 * @return string
 */
function semnews_action_url( $action, $token, $id ) {
	return add_query_arg(
		array(
			'semnews_action' => rawurlencode( $action ),
			'semnews_id'     => (int) $id,
			'semnews_token'  => rawurlencode( $token ),
		),
		home_url( '/' )
	);
}

/**
 * Locate a template file, allowing theme overrides via the
 * `quintessential-newsletters/` theme subfolder.
 *
 * @param string $template Relative path under templates/, e.g. "emails/confirmation.php".
 * @return string Absolute path to the template, or '' if none found.
 */
function semnews_locate_template( $template ) {
	$template = ltrim( $template, '/' );

	$candidates = array(
		trailingslashit( get_stylesheet_directory() ) . 'quintessential-newsletters/' . $template,
		trailingslashit( get_template_directory() ) . 'quintessential-newsletters/' . $template,
		SEMNEWS_PLUGIN_DIR . 'templates/' . $template,
	);

	foreach ( $candidates as $candidate ) {
		if ( file_exists( $candidate ) ) {
			return $candidate;
		}
	}

	return '';
}

/**
 * Locate and render a template, allowing theme overrides via the
 * `quintessential-newsletters/` theme subfolder.
 *
 * @param string $template Relative path under templates/, e.g. "emails/confirmation.php".
 * @param array  $args     Variables exposed to the template.
 * @return string Rendered output.
 */
function semnews_render_template( $template, $args = array() ) {
	$file = semnews_locate_template( $template );

	if ( ! $file ) {
		return '';
	}

	if ( ! empty( $args ) ) {
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled, known keys.
		extract( $args, EXTR_SKIP );
	}

	ob_start();
	include $file;
	return ob_get_clean();
}

/**
 * wp_kses() allowlist for plugin-rendered signup forms and opt-in pages.
 *
 * Covers exactly the markup the plugin's own form/opt-in renderers produce
 * (form fields, nonce inputs, data tables, notices), so their HTML can be
 * escaped late — at echo time — without stripping anything legitimate.
 *
 * @return array
 */
function semnews_allowed_form_html() {
	$common = array(
		'class'       => true,
		'id'          => true,
		'style'       => true,
		'role'        => true,
		'aria-hidden' => true,
		'aria-live'   => true,
		'aria-label'  => true,
	);

	$allowed = array(
		'div'      => $common,
		'p'        => $common,
		'h1'       => $common,
		'h2'       => $common,
		'h3'       => $common,
		'span'     => $common,
		'small'    => $common,
		'strong'   => $common,
		'em'       => $common,
		'br'       => $common,
		'table'    => array_merge( $common, array( 'cellpadding' => true, 'cellspacing' => true, 'width' => true ) ),
		'thead'    => $common,
		'tbody'    => $common,
		'tr'       => $common,
		'th'       => array_merge( $common, array( 'scope' => true, 'colspan' => true ) ),
		'td'       => array_merge( $common, array( 'colspan' => true ) ),
		'a'        => array_merge( $common, array( 'href' => true, 'target' => true, 'rel' => true ) ),
		'label'    => array_merge( $common, array( 'for' => true ) ),
		'form'     => array_merge( $common, array( 'method' => true, 'action' => true, 'novalidate' => true ) ),
		'input'    => array_merge(
			$common,
			array(
				'type'         => true,
				'name'         => true,
				'value'        => true,
				'checked'      => true,
				'required'     => true,
				'tabindex'     => true,
				'autocomplete' => true,
				'placeholder'  => true,
			)
		),
		'button'   => array_merge( $common, array( 'type' => true, 'name' => true, 'value' => true ) ),
		'textarea' => array_merge( $common, array( 'name' => true, 'rows' => true ) ),
	);

	/**
	 * Filter the kses allowlist used when echoing plugin-rendered form HTML.
	 *
	 * @param array $allowed Allowed tags/attributes (wp_kses format).
	 */
	return apply_filters( 'semnews_allowed_form_html', $allowed );
}

/**
 * wp_kses() allowlist for HTML email bodies.
 *
 * Matches SEMNEWS_Campaigns::sanitize_body() (post allowlist + the inline
 * style/class email clients require) plus the presentational table/img
 * attributes the built-in email templates use, so escaping the body again
 * at output time is lossless.
 *
 * @return array
 */
function semnews_allowed_email_html() {
	$allowed = wp_kses_allowed_html( 'post' );

	foreach ( array( 'p', 'div', 'span', 'a', 'td', 'th', 'table', 'tr', 'img', 'h1', 'h2', 'h3', 'h4' ) as $tag ) {
		if ( ! isset( $allowed[ $tag ] ) ) {
			$allowed[ $tag ] = array();
		}
		$allowed[ $tag ]['style'] = true;
		$allowed[ $tag ]['class'] = true;
	}

	foreach ( array( 'table', 'tr', 'td', 'th' ) as $tag ) {
		$allowed[ $tag ]['role']        = true;
		$allowed[ $tag ]['align']       = true;
		$allowed[ $tag ]['valign']      = true;
		$allowed[ $tag ]['width']       = true;
		$allowed[ $tag ]['height']      = true;
		$allowed[ $tag ]['bgcolor']     = true;
		$allowed[ $tag ]['cellpadding'] = true;
		$allowed[ $tag ]['cellspacing'] = true;
		$allowed[ $tag ]['border']      = true;
	}

	$allowed['img'] = array_merge(
		isset( $allowed['img'] ) ? $allowed['img'] : array(),
		array(
			'src'    => true,
			'alt'    => true,
			'width'  => true,
			'height' => true,
			'border' => true,
		)
	);

	/**
	 * Filter the kses allowlist used when echoing email body HTML.
	 *
	 * @param array $allowed Allowed tags/attributes (wp_kses format).
	 */
	return apply_filters( 'semnews_allowed_email_html', $allowed );
}

/**
 * wp_kses() allowlist for a complete HTML email document (browser previews).
 *
 * The email body allowlist plus the document chrome the shared layout emits
 * (html/head/meta/title/body). The doctype is printed separately by the
 * caller because kses always strips it.
 *
 * @return array
 */
function semnews_allowed_email_document_html() {
	$allowed = semnews_allowed_email_html();

	$allowed['html']  = array(
		'lang'  => true,
		'dir'   => true,
		'class' => true,
	);
	$allowed['head']  = array();
	$allowed['title'] = array();
	$allowed['meta']  = array(
		'charset'    => true,
		'name'       => true,
		'content'    => true,
		'http-equiv' => true,
	);
	$allowed['body']  = array(
		'style' => true,
		'class' => true,
	);

	return $allowed;
}
