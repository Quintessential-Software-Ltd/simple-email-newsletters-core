<?php
/**
 * Public opt-in endpoints: confirm, unsubscribe, preferences.
 *
 * Links carry ?semnews_action=...&semnews_id=...&semnews_token=... and are handled early on
 * `template_redirect`. Tokens are verified in constant time. One-click
 * unsubscribe also accepts POST (RFC 8058 List-Unsubscribe-Post) with no login
 * and no friction.
 *
 * @package SimpleEmailNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Opt-in endpoint controller.
 */
class SEMNEWS_Optin {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'template_redirect', array( $this, 'handle' ) );
	}

	/**
	 * Dispatch an incoming action link.
	 *
	 * @return void
	 */
	public function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- these are links opened from emails (confirm / unsubscribe / preferences); the per-subscriber secret token below is the credential. Nonces expire and would break emailed links.
		if ( ! isset( $_REQUEST['semnews_action'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_REQUEST['semnews_action'] ) );
		$id     = isset( $_REQUEST['semnews_id'] ) ? absint( $_REQUEST['semnews_id'] ) : 0;
		$token  = isset( $_REQUEST['semnews_token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['semnews_token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Links inside a [TEST] email or browser preview carry the dummy
		// "preview" token (there is no real recipient to act on). Explain that
		// instead of showing a scary invalid-link error to the site owner.
		if ( 0 === $id && 'preview' === $token ) {
			$this->render_page(
				'info',
				__( 'This link came from a test email or preview, so it is not connected to a real subscriber — there is nothing to unsubscribe or show. In a real newsletter, every recipient gets their own secure link, and unsubscribing works with one click.', 'quintessential-newsletters' )
			);
			return;
		}

		if ( ! $id || '' === $token ) {
			$this->render_page( 'error', __( 'This link is invalid or has expired.', 'quintessential-newsletters' ) );
			return;
		}

		$subscriber = SEMNEWS_Subscribers::get_verified( $id, $token );

		if ( ! $subscriber ) {
			$this->render_page( 'error', __( 'This link is invalid or has expired.', 'quintessential-newsletters' ) );
			return;
		}

		switch ( $action ) {
			case 'confirm':
				$this->handle_confirm( $subscriber );
				break;
			case 'unsubscribe':
				$this->handle_unsubscribe( $subscriber );
				break;
			case 'preferences':
				$this->handle_preferences( $subscriber );
				break;
			case 'data':
				$this->handle_data( $subscriber );
				break;
			default:
				$this->render_page( 'error', __( 'Unknown request.', 'quintessential-newsletters' ) );
		}
	}

	/**
	 * Whether the current request is a POST.
	 *
	 * @return bool
	 */
	protected function is_post() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		return 'POST' === $method;
	}

	/**
	 * Render a one-button confirmation page that POSTs back to the same action.
	 *
	 * State-changing/PII actions only run on the POST, so link-prefetchers, email
	 * scanners and corporate anti-virus (which issue GETs and don't submit forms)
	 * cannot trigger them, and a leaked action URL in a log/Referer does nothing
	 * on its own. The form action URL already carries the verified token.
	 *
	 * @param object $subscriber  Subscriber.
	 * @param string $action      Action (confirm|unsubscribe|data).
	 * @param string $type        Page type/class.
	 * @param string $intro       Intro text.
	 * @param string $button      Button label.
	 * @return void
	 */
	protected function action_button_page( $subscriber, $action, $type, $intro, $button ) {
		ob_start();
		?>
		<p><?php echo esc_html( $intro ); ?></p>
		<form method="post" action="<?php echo esc_url( semnews_action_url( $action, $subscriber->token, $subscriber->id ) ); ?>">
			<input type="hidden" name="semnews_do" value="1" />
			<p><button type="submit" class="semnews-button"><?php echo esc_html( $button ); ?></button></p>
		</form>
		<?php
		$this->render_page( $type, ob_get_clean(), false );
	}

	/**
	 * Confirm a double opt-in (state change on POST only).
	 *
	 * @param object $subscriber Subscriber.
	 * @return void
	 */
	protected function handle_confirm( $subscriber ) {
		if ( ! $this->is_post() ) {
			$this->action_button_page(
				$subscriber,
				'confirm',
				'confirm',
				__( 'Please confirm you want to subscribe to our newsletter.', 'quintessential-newsletters' ),
				__( 'Confirm my subscription', 'quintessential-newsletters' )
			);
			return;
		}

		SEMNEWS_Subscribers::confirm( $subscriber );

		$this->render_page(
			'confirmed',
			__( 'Thank you! Your subscription is now confirmed.', 'quintessential-newsletters' )
		);
	}

	/**
	 * Unsubscribe.
	 *
	 * The mailbox one-click button (RFC 8058 List-Unsubscribe-Post) and our own
	 * one-button page both POST, so the unsubscribe stays effortless — but a plain
	 * GET (link-prefetchers, scanners) no longer silently unsubscribes a real
	 * person; it shows a single confirm button instead.
	 *
	 * @param object $subscriber Subscriber.
	 * @return void
	 */
	protected function handle_unsubscribe( $subscriber ) {
		// RFC 8058 one-click: the mail client POSTs List-Unsubscribe=One-Click.
		$one_click = isset( $_POST['List-Unsubscribe'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- token-authenticated endpoint.

		if ( ! $this->is_post() ) {
			$this->action_button_page(
				$subscriber,
				'unsubscribe',
				'unsubscribed',
				__( 'Click below to unsubscribe. You will not receive any further newsletters.', 'quintessential-newsletters' ),
				__( 'Unsubscribe', 'quintessential-newsletters' )
			);
			return;
		}

		SEMNEWS_Subscribers::unsubscribe( $subscriber );

		if ( $one_click ) {
			// One-click from a mail client: no HTML needed, just 200.
			status_header( 200 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo 'Unsubscribed';
			exit;
		}

		$this->render_page(
			'unsubscribed',
			__( 'You have been unsubscribed. We are sorry to see you go — you will not receive any further newsletters.', 'quintessential-newsletters' )
		);
	}

	/**
	 * Preference centre: lets a subscriber resubscribe or unsubscribe.
	 *
	 * @param object $subscriber Subscriber.
	 * @return void
	 */
	protected function handle_preferences( $subscriber ) {
		if ( isset( $_POST['semnews_prefs_submit'] ) ) {
			check_admin_referer( 'semnews_prefs_' . $subscriber->id, 'semnews_prefs_nonce' );

			// Let the subscriber correct their own name.
			if ( isset( $_POST['semnews_name'] ) ) {
				$new_name = sanitize_text_field( wp_unslash( $_POST['semnews_name'] ) );
				if ( $new_name !== (string) $subscriber->name ) {
					SEMNEWS_Subscribers::update(
						(int) $subscriber->id,
						array(
							'name'       => $new_name,
							'updated_at' => current_time( 'mysql', true ),
						)
					);
				}
			}

			$choice = isset( $_POST['semnews_subscribed'] ) ? '1' : '0';

			if ( '1' === $choice ) {
				if ( SEMNEWS_Subscribers::STATUS_SUBSCRIBED !== $subscriber->status ) {
					SEMNEWS_Subscribers::confirm( $subscriber );
				}
				$message = __( 'Your preferences have been saved. You are subscribed.', 'quintessential-newsletters' );
			} else {
				SEMNEWS_Subscribers::unsubscribe( $subscriber );
				$message = __( 'Your preferences have been saved. You are unsubscribed.', 'quintessential-newsletters' );
			}

			/**
			 * Fires after the preference centre saved its core fields, while
			 * the nonce-verified POST is still available — lets add-ons save
			 * their own preference fields (e.g. the Pro engagement-tracking
			 * choice).
			 *
			 * @param object $subscriber Subscriber row (pre-save state).
			 */
			do_action( 'semnews_preferences_saved', $subscriber );

			$subscriber = SEMNEWS_Subscribers::get( $subscriber->id );
			$this->render_preferences_form( $subscriber, $message );
		}

		$this->render_preferences_form( $subscriber );
	}

	/**
	 * Self-service transparency: show a subscriber their own record and consent
	 * history, and let them download it as JSON. All via their signed token link —
	 * no login. This is honest-by-design: people can see exactly what we hold.
	 *
	 * @param object $subscriber Subscriber.
	 * @return void
	 */
	protected function handle_data( $subscriber ) {
		// JSON download (token already verified in handle()).
		if ( isset( $_POST['semnews_download'] ) ) {
			check_admin_referer( 'semnews_data_' . $subscriber->id, 'semnews_data_nonce' );
			$this->stream_data_json( $subscriber );
		}

		// Only render the personal data on a deliberate POST. A GET (which is what
		// link-prefetchers, scanners and a leaked URL in a log/Referer produce)
		// shows a single button instead, so PII is never dumped from a bare GET.
		if ( ! $this->is_post() ) {
			$this->action_button_page(
				$subscriber,
				'data',
				'data',
				__( 'View the personal data we hold about you and your consent history.', 'quintessential-newsletters' ),
				__( 'Show my data', 'quintessential-newsletters' )
			);
			return;
		}

		$bases   = SEMNEWS_Subscribers::bases();
		$statuses = SEMNEWS_Subscribers::statuses();
		$history = SEMNEWS_Consent_Log::get_for_subscriber( (int) $subscriber->id );

		ob_start();
		?>
		<p><?php echo esc_html( sprintf( /* translators: %s: email */ __( 'Here is everything we hold for %s.', 'quintessential-newsletters' ), $subscriber->email ) ); ?></p>

		<h2 style="font-size:16px;"><?php esc_html_e( 'Your details', 'quintessential-newsletters' ); ?></h2>
		<table class="semnews-data-table" cellpadding="0" cellspacing="0">
			<tbody>
				<tr><th><?php esc_html_e( 'Email', 'quintessential-newsletters' ); ?></th><td><?php echo esc_html( $subscriber->email ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Name', 'quintessential-newsletters' ); ?></th><td><?php echo esc_html( $subscriber->name ? $subscriber->name : '—' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Status', 'quintessential-newsletters' ); ?></th><td><?php echo esc_html( isset( $statuses[ $subscriber->status ] ) ? $statuses[ $subscriber->status ] : $subscriber->status ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Lawful basis', 'quintessential-newsletters' ); ?></th><td><?php echo esc_html( isset( $bases[ $subscriber->consent_basis ] ) ? $bases[ $subscriber->consent_basis ] : $subscriber->consent_basis ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Subscribed', 'quintessential-newsletters' ); ?></th><td><?php echo esc_html( $subscriber->created_at ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Confirmed', 'quintessential-newsletters' ); ?></th><td><?php echo esc_html( $subscriber->confirmed_at ? $subscriber->confirmed_at : '—' ); ?></td></tr>
				<?php if ( $subscriber->consent_text ) : ?>
					<tr><th><?php esc_html_e( 'You agreed to', 'quintessential-newsletters' ); ?></th><td><?php echo esc_html( $subscriber->consent_text ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>

		<h2 style="font-size:16px;margin-top:24px;"><?php esc_html_e( 'Your consent history', 'quintessential-newsletters' ); ?></h2>
		<?php if ( empty( $history ) ) : ?>
			<p><?php esc_html_e( 'No events recorded.', 'quintessential-newsletters' ); ?></p>
		<?php else : ?>
			<table class="semnews-data-table" cellpadding="0" cellspacing="0">
				<thead><tr><th><?php esc_html_e( 'When', 'quintessential-newsletters' ); ?></th><th><?php esc_html_e( 'Event', 'quintessential-newsletters' ); ?></th><th><?php esc_html_e( 'Source', 'quintessential-newsletters' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $history as $event ) : ?>
						<tr>
							<td><?php echo esc_html( $event['created_at'] ); ?></td>
							<td><?php echo esc_html( $event['event'] ); ?></td>
							<td><?php echo esc_html( $event['source'] ? $event['source'] : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( semnews_action_url( 'data', $subscriber->token, $subscriber->id ) ); ?>" style="margin-top:24px;">
			<?php wp_nonce_field( 'semnews_data_' . $subscriber->id, 'semnews_data_nonce' ); ?>
			<button type="submit" name="semnews_download" value="1" class="semnews-button"><?php esc_html_e( 'Download my data (JSON)', 'quintessential-newsletters' ); ?></button>
		</form>
		<p style="margin-top:16px;">
			<a href="<?php echo esc_url( semnews_action_url( 'preferences', $subscriber->token, $subscriber->id ) ); ?>"><?php esc_html_e( 'Manage your subscription', 'quintessential-newsletters' ); ?></a>
		</p>
		<?php
		$this->render_page( 'data', ob_get_clean(), false );
	}

	/**
	 * Stream the subscriber's data as a JSON download and exit.
	 *
	 * @param object $subscriber Subscriber.
	 * @return void
	 */
	protected function stream_data_json( $subscriber ) {
		$payload = array(
			'email'           => $subscriber->email,
			'name'            => $subscriber->name,
			'status'          => $subscriber->status,
			'consent_basis'   => $subscriber->consent_basis,
			'consent_text'    => $subscriber->consent_text,
			'consent_version' => $subscriber->consent_version,
			'source'          => $subscriber->source,
			'created_at'      => $subscriber->created_at,
			'confirmed_at'    => $subscriber->confirmed_at,
			'unsubscribed_at' => $subscriber->unsubscribed_at,
			'consent_history' => SEMNEWS_Consent_Log::get_for_subscriber( (int) $subscriber->id ),
		);

		/**
		 * Filter the subscriber's self-service data export, so add-ons that
		 * store extra data about them (e.g. Pro engagement clicks) are
		 * included — GDPR access rights cover everything, not just core.
		 *
		 * @param array  $payload    Export payload.
		 * @param object $subscriber Subscriber row.
		 */
		$payload = apply_filters( 'semnews_subscriber_export_data', $payload, $subscriber );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=my-newsletter-data.json' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON body.
		exit;
	}

	/**
	 * Render the preferences form page.
	 *
	 * @param object $subscriber Subscriber.
	 * @param string $message    Optional notice.
	 * @return void
	 */
	protected function render_preferences_form( $subscriber, $message = '' ) {
		$is_subscribed = ( SEMNEWS_Subscribers::STATUS_SUBSCRIBED === $subscriber->status );

		ob_start();
		?>
		<?php if ( $message ) : ?>
			<p class="semnews-notice"><?php echo esc_html( $message ); ?></p>
		<?php endif; ?>
		<p><?php echo esc_html( sprintf( /* translators: %s: email */ __( 'Managing preferences for %s', 'quintessential-newsletters' ), $subscriber->email ) ); ?></p>
		<form method="post" action="<?php echo esc_url( semnews_action_url( 'preferences', $subscriber->token, $subscriber->id ) ); ?>">
			<?php wp_nonce_field( 'semnews_prefs_' . $subscriber->id, 'semnews_prefs_nonce' ); ?>
			<p>
				<label for="semnews-prefs-name"><?php esc_html_e( 'Your name', 'quintessential-newsletters' ); ?></label><br />
				<input type="text" id="semnews-prefs-name" name="semnews_name" value="<?php echo esc_attr( (string) $subscriber->name ); ?>" style="width:100%;max-width:320px;padding:8px;border:1px solid #ccd0d4;border-radius:4px;" />
			</p>
			<label>
				<input type="checkbox" name="semnews_subscribed" value="1" <?php checked( $is_subscribed ); ?> />
				<?php esc_html_e( 'Yes, I want to receive the newsletter', 'quintessential-newsletters' ); ?>
			</label>
			<?php
			/**
			 * Lets add-ons render extra preference-centre fields (e.g. the
			 * Pro engagement-tracking choice). Inside the nonce-protected
			 * form, before the save button.
			 *
			 * @param object $subscriber Subscriber row.
			 */
			do_action( 'semnews_preferences_fields', $subscriber );
			?>
			<p>
				<button type="submit" name="semnews_prefs_submit" value="1" class="semnews-button">
					<?php esc_html_e( 'Save preferences', 'quintessential-newsletters' ); ?>
				</button>
			</p>
		</form>
		<form method="post" action="<?php echo esc_url( semnews_action_url( 'data', $subscriber->token, $subscriber->id ) ); ?>" style="margin-top:16px;">
			<input type="hidden" name="semnews_do" value="1" />
			<button type="submit" class="semnews-link-button" style="background:none;border:0;color:#2271b1;cursor:pointer;text-decoration:underline;padding:0;font-size:inherit;"><?php esc_html_e( 'See the data we hold about you', 'quintessential-newsletters' ); ?></button>
		</form>
		<?php
		$body = ob_get_clean();

		$this->render_page( 'preferences', $body, false );
	}

	/**
	 * Render a minimal standalone page for an opt-in result and exit.
	 *
	 * @param string $type    Result type (for the body class).
	 * @param string $message Message or HTML body.
	 * @param bool   $escape  Whether to escape $message (false when it is HTML).
	 * @return void
	 */
	protected function render_page( $type, $message, $escape = true ) {
		nocache_headers();

		// Don't leak the per-subscriber token to any linked third party via Referer.
		if ( ! headers_sent() ) {
			header( 'Referrer-Policy: no-referrer' );
		}

		$title   = get_bloginfo( 'name' );
		$heading = semnews_get_option( 'company_name' );

		$content = $escape ? '<p>' . esc_html( $message ) . '</p>' : $message;

		// Allow themes/sites to fully override via template. The template is
		// included directly (with $type / $title / $heading / $content in
		// scope) and is responsible for escaping its own output.
		$override = semnews_locate_template( 'optin-page.php' );
		if ( '' !== $override ) {
			include $override;
			exit;
		}

		// Built-in fallback page. Rendered outside the theme, so the stylesheet
		// is registered here and printed straight into the standalone <head>.
		wp_register_style( 'semnews-optin', SEMNEWS_PLUGIN_URL . 'assets/css/optin.css', array(), SEMNEWS_VERSION );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<meta name="referrer" content="no-referrer" />
	<title><?php echo esc_html( $heading ); ?></title>
	<?php wp_print_styles( 'semnews-optin' ); ?>
</head>
<body class="semnews-optin semnews-optin-<?php echo esc_attr( $type ); ?>">
	<div class="semnews-card">
		<h1><?php echo esc_html( $heading ); ?></h1>
		<?php echo wp_kses( $content, semnews_allowed_form_html() ); ?>
		<p style="margin-top:24px;"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; <?php echo esc_html( get_bloginfo( 'name' ) ); ?></a></p>
	</div>
</body>
</html>
		<?php
		exit;
	}
}
