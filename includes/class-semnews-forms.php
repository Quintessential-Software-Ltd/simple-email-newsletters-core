<?php
/**
 * Subscription form rendering and submission handling.
 *
 * Exposes the [semnews_newsletter] shortcode and handles both AJAX and no-JS POST
 * submissions. Anti-spam: honeypot field, minimum render-time check, per-IP
 * rate limiting. Anti-CSRF: nonce. The consent checkbox is never pre-ticked.
 *
 * @package QuintessentialNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forms controller.
 */
class SEMNEWS_Forms {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_shortcode( 'semnews_newsletter', array( $this, 'shortcode' ) );

		// No-JS / standard POST.
		add_action( 'admin_post_nopriv_semnews_subscribe', array( $this, 'handle_post' ) );
		add_action( 'admin_post_semnews_subscribe', array( $this, 'handle_post' ) );

		// AJAX.
		add_action( 'wp_ajax_nopriv_semnews_subscribe', array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_semnews_subscribe', array( $this, 'handle_ajax' ) );
	}

	/**
	 * Render the [semnews_newsletter] shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			/**
			 * Lets add-ons accept extra shortcode/block attributes (e.g. the
			 * Pro `list=""` attribute assigning signups to a list).
			 *
			 * @param array $defaults Attribute defaults.
			 */
			apply_filters(
				'semnews_form_atts_defaults',
				array(
					'title'       => __( 'Subscribe to our newsletter', 'quintessential-newsletters' ),
					'description' => '',
					'show_name'   => 'true',
					'button'      => __( 'Subscribe', 'quintessential-newsletters' ),
					'source'      => '',
				)
			),
			$atts,
			'semnews_newsletter'
		);

		return $this->render_form( $atts );
	}

	/**
	 * Build the form HTML.
	 *
	 * @param array  $atts   Display attributes.
	 * @param array  $notice { type, message } optional inline result for no-JS.
	 * @return string
	 */
	public function render_form( $atts, $notice = array() ) {
		$show_name    = filter_var( $atts['show_name'], FILTER_VALIDATE_BOOLEAN );
		$consent_text = semnews_get_option( 'consent_text' );
		$privacy_id   = (int) semnews_get_option( 'privacy_policy_page' );
		// Fall back to WordPress' designated privacy policy page so the link still
		// appears even if the plugin setting was left as "None".
		if ( ! $privacy_id ) {
			$privacy_id = (int) get_option( 'wp_page_for_privacy_policy', 0 );
		}
		$privacy_url  = $privacy_id ? get_permalink( $privacy_id ) : '';
		$source       = $atts['source'] ? $atts['source'] : 'shortcode';
		$form_id      = 'semnews-form-' . wp_rand( 1000, 9999 );

		// Time check: forms submitted within 3s of render are almost always bots.
		$rendered_at = time();

		ob_start();
		?>
		<div class="semnews-form-wrap">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h3 class="semnews-form-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>
			<?php if ( ! empty( $atts['description'] ) ) : ?>
				<p class="semnews-form-description"><?php echo esc_html( $atts['description'] ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $notice['message'] ) ) : ?>
				<div class="semnews-message semnews-message-<?php echo esc_attr( $notice['type'] ); ?>" role="status">
					<?php echo esc_html( $notice['message'] ); ?>
				</div>
			<?php endif; ?>

			<form class="semnews-form" id="<?php echo esc_attr( $form_id ); ?>" method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

				<input type="hidden" name="action" value="semnews_subscribe" />
				<input type="hidden" name="semnews_source" value="<?php echo esc_attr( $source ); ?>" />
				<input type="hidden" name="semnews_rendered_at" value="<?php echo esc_attr( $rendered_at ); ?>" />
				<input type="hidden" name="semnews_redirect" value="<?php echo esc_url( $this->current_url() ); ?>" />
				<?php
				/**
				 * Lets add-ons print extra hidden fields into the signup form
				 * (e.g. the Pro list assignment). Escape your own output.
				 *
				 * @param array $atts Resolved form attributes.
				 */
				do_action( 'semnews_form_hidden_fields', $atts );
				?>
				<?php wp_nonce_field( 'semnews_subscribe', 'semnews_nonce' ); ?>

				<?php // Honeypot: visually hidden, must stay empty. Label it plausibly for bots. ?>
				<div class="semnews-hp" aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;overflow:hidden;">
					<label for="<?php echo esc_attr( $form_id ); ?>-website"><?php esc_html_e( 'Website', 'quintessential-newsletters' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $form_id ); ?>-website" name="semnews_website" tabindex="-1" autocomplete="off" value="" />
				</div>

				<?php if ( $show_name ) : ?>
					<p class="semnews-field semnews-field-name">
						<label for="<?php echo esc_attr( $form_id ); ?>-name"><?php esc_html_e( 'Name', 'quintessential-newsletters' ); ?></label>
						<input type="text" id="<?php echo esc_attr( $form_id ); ?>-name" name="semnews_name" autocomplete="name" value="" />
					</p>
				<?php endif; ?>

				<p class="semnews-field semnews-field-email">
					<label for="<?php echo esc_attr( $form_id ); ?>-email">
						<?php esc_html_e( 'Email address', 'quintessential-newsletters' ); ?> <span class="semnews-required" aria-hidden="true">*</span>
					</label>
					<input type="email" id="<?php echo esc_attr( $form_id ); ?>-email" name="semnews_email" required autocomplete="email" value="" />
				</p>

				<p class="semnews-field semnews-field-consent">
					<label>
						<?php // Never checked by default — valid GDPR consent requires a clear affirmative action. ?>
						<input type="checkbox" name="semnews_consent" value="1" required />
						<span class="semnews-consent-text">
							<?php echo esc_html( $consent_text ); ?>
							<?php if ( $privacy_url ) : ?>
								<a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener">
									<?php esc_html_e( 'Privacy policy', 'quintessential-newsletters' ); ?>
								</a>
							<?php endif; ?>
						</span>
					</label>
				</p>

				<p class="semnews-field semnews-field-submit">
					<button type="submit" class="semnews-submit"><?php echo esc_html( $atts['button'] ); ?></button>
				</p>

				<div class="semnews-form-feedback" aria-live="polite"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate a submission and run the subscribe. Shared by AJAX + POST.
	 *
	 * @return array { success, code, message, status }
	 */
	protected function process_submission() {
		// Nonce.
		$nonce = isset( $_POST['semnews_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['semnews_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'semnews_subscribe' ) ) {
			return $this->result( false, 'bad_nonce', __( 'Your session expired. Please refresh the page and try again.', 'quintessential-newsletters' ) );
		}

		// Honeypot.
		if ( ! empty( $_POST['semnews_website'] ) ) {
			// Pretend success so bots cannot tell.
			return $this->result( true, 'ok', semnews_get_option( 'success_message' ), SEMNEWS_Subscribers::STATUS_PENDING );
		}

		// Time trap.
		$rendered_at = isset( $_POST['semnews_rendered_at'] ) ? absint( $_POST['semnews_rendered_at'] ) : 0;
		if ( $rendered_at && ( time() - $rendered_at ) < 3 ) {
			return $this->result( true, 'ok', semnews_get_option( 'success_message' ), SEMNEWS_Subscribers::STATUS_PENDING );
		}

		// Rate limit per IP: max 5 attempts / 10 minutes.
		$ip  = semnews_get_ip();
		$key = 'semnews_rl_' . md5( $ip ? $ip : 'noip' );
		$hits = (int) get_transient( $key );
		if ( $hits >= 5 ) {
			/**
			 * Fires when a signup is blocked by per-IP rate limiting, for SIEM /
			 * alerting on form abuse.
			 *
			 * @param string $type    Event type.
			 * @param array  $context Context (client IP).
			 */
			do_action( 'semnews_security_event', 'signup_rate_limited', array( 'ip' => $ip ) );
			return $this->result( false, 'rate_limited', __( 'Too many attempts. Please try again in a few minutes.', 'quintessential-newsletters' ) );
		}
		set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );

		// Consent must be present.
		if ( empty( $_POST['semnews_consent'] ) ) {
			return $this->result( false, 'no_consent', __( 'Please tick the consent box to subscribe.', 'quintessential-newsletters' ) );
		}

		$email = isset( $_POST['semnews_email'] ) ? sanitize_email( wp_unslash( $_POST['semnews_email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			return $this->result( false, 'invalid_email', __( 'Please enter a valid email address.', 'quintessential-newsletters' ) );
		}

		$name         = isset( $_POST['semnews_name'] ) ? sanitize_text_field( wp_unslash( $_POST['semnews_name'] ) ) : '';
		$source       = isset( $_POST['semnews_source'] ) ? sanitize_text_field( wp_unslash( $_POST['semnews_source'] ) ) : 'form';
		// Use the server-side configured consent text, not the submitted value, as the canonical record.
		$consent_text = semnews_get_option( 'consent_text' );

		$res = SEMNEWS_Subscribers::subscribe(
			array(
				'email'        => $email,
				'name'         => $name,
				'consent_text' => $consent_text,
				'source'       => $source,
			)
		);

		if ( ! $res['success'] ) {
			if ( 'invalid_email' === $res['code'] ) {
				return $this->result( false, 'invalid_email', __( 'Please enter a valid email address.', 'quintessential-newsletters' ) );
			}
			return $this->result( false, 'error', __( 'Something went wrong. Please try again.', 'quintessential-newsletters' ) );
		}

		$double = (bool) semnews_get_option( 'double_optin', 1 );
		$message = $double ? semnews_get_option( 'success_message' ) : semnews_get_option( 'success_message_single' );

		return $this->result( true, 'ok', $message, $res['status'] );
	}

	/**
	 * Handle a standard (no-JS) POST and redirect back with a result flag.
	 *
	 * @return void
	 */
	public function handle_post() {
		$result   = $this->process_submission();

		// Remember a successful signup so display placements stop showing (no-JS path).
		if ( $result['success'] && ! headers_sent() ) {
			setcookie( SEMNEWS_Display::SUBSCRIBED_COOKIE, '1', time() + YEAR_IN_SECONDS, '/' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- public signup form: nonces break full-page caching and add nothing for an unauthenticated action; abuse is limited by the honeypot, time-trap, per-IP rate limit and double opt-in, and the redirect is locked to this site below.
		$redirect = isset( $_POST['semnews_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['semnews_redirect'] ) ) : home_url( '/' );

		// Only allow redirects back to this site.
		$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );

		$redirect = add_query_arg(
			array(
				'semnews_result' => $result['success'] ? 'success' : 'error',
				'semnews_code'   => rawurlencode( $result['code'] ),
			),
			$redirect
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle an AJAX submission.
	 *
	 * @return void
	 */
	public function handle_ajax() {
		$result = $this->process_submission();

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message' => $result['message'],
					'status'  => $result['status'],
				)
			);
		}

		wp_send_json_error(
			array(
				'message' => $result['message'],
				'code'    => $result['code'],
			)
		);
	}

	/**
	 * Helper to build a result array.
	 *
	 * @param bool   $success Success flag.
	 * @param string $code    Machine code.
	 * @param string $message Human message.
	 * @param string $status  Resulting subscriber status.
	 * @return array
	 */
	protected function result( $success, $code, $message, $status = '' ) {
		return compact( 'success', 'code', 'message', 'status' );
	}

	/**
	 * Current front-end URL (for no-JS redirect back).
	 *
	 * @return string
	 */
	protected function current_url() {
		$req = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return home_url( $req );
	}
}
