<?php
/**
 * Inbound bounce / complaint webhook.
 *
 * Most hosts send through wp_mail() and never see asynchronous bounces or spam
 * complaints, so this exposes one authenticated endpoint your mail provider (or
 * a feedback loop / FBL forwarder) can POST to. Verified events are fed straight
 * into the suppression list via SEMNEWS_Subscribers, so a bounced or complaining
 * address is never mailed again.
 *
 * Endpoint:  POST {site}/wp-json/semnews/v1/webhook
 * Auth:      header `X-SEMNEWS-Secret: <secret>` or `?secret=<secret>` (constant-time)
 * Body:      JSON { "event": "bounce|complaint|soft_bounce", "email": "...", "type": "hard|soft" }
 *
 * Map any ESP-specific payload to that shape with the `semnews_webhook_payload` filter.
 *
 * @package SimpleEmailNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Webhook controller.
 */
class SEMNEWS_Webhook {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wp_mail_failed', array( $this, 'on_mail_failed' ) );
	}

	/**
	 * The shared webhook secret (lazily created so upgrades get one too).
	 *
	 * @return string
	 */
	public static function secret() {
		$secret = get_option( 'semnews_webhook_secret' );
		if ( ! $secret ) {
			$secret = wp_generate_password( 40, false, false );
			update_option( 'semnews_webhook_secret', $secret, false );
		}
		return $secret;
	}

	/**
	 * Rotate the secret (invalidates the old URL).
	 *
	 * @return string New secret.
	 */
	public static function rotate_secret() {
		$secret = wp_generate_password( 40, false, false );
		update_option( 'semnews_webhook_secret', $secret, false );
		return $secret;
	}

	/**
	 * The full webhook URL.
	 *
	 * @return string
	 */
	public static function url() {
		return rest_url( 'semnews/v1/webhook' );
	}

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'semnews/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'authorize' ),
			)
		);
	}

	/**
	 * Authorise via the shared secret (constant-time compare).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function authorize( $request ) {
		// Preferred: a request header (kept out of URLs/logs).
		$provided = $request->get_header( 'x-semnews-secret' );

		// Also accept HTTP Basic Auth, so the secret can travel in the
		// Authorization header (not the query string) for providers that support it.
		if ( ! $provided ) {
			$auth = (string) $request->get_header( 'authorization' );
			if ( 0 === stripos( $auth, 'basic ' ) ) {
				$decoded = base64_decode( trim( substr( $auth, 6 ) ), true );
				if ( false !== $decoded ) {
					$provided = ( false !== strpos( $decoded, ':' ) ) ? explode( ':', $decoded, 2 )[1] : $decoded;
				}
			}
		}

		// Last-resort query-string fallback for providers that can only set a URL
		// (SendGrid, Amazon SNS, Mailgun). It can appear in server logs, so it is
		// filterable and a security-conscious admin can switch it off.
		if ( ! $provided && apply_filters( 'semnews_webhook_allow_url_secret', true ) ) {
			$provided = $request->get_param( 'secret' );
		}

		$provided = is_string( $provided ) ? $provided : '';
		$ok       = hash_equals( self::secret(), $provided );

		if ( ! $ok ) {
			/**
			 * Fires on a failed webhook authentication so a SIEM / alerting hook
			 * can detect brute-force or abuse.
			 *
			 * @param string $type    Event type.
			 * @param array  $context Context (client IP).
			 */
			do_action( 'semnews_security_event', 'webhook_auth_failed', array( 'ip' => function_exists( 'semnews_get_ip' ) ? semnews_get_ip() : '' ) );
		}

		return $ok;
	}

	/**
	 * Handle a verified webhook call.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle( $request ) {
		$events = $this->parse_request( $request );

		// Amazon SNS subscription handshake (SES) — confirmed, nothing to record.
		if ( 'subscription_confirmed' === $events ) {
			return new WP_REST_Response( array( 'ok' => true, 'subscription' => 'confirmed' ), 200 );
		}

		if ( ! is_array( $events ) ) {
			$events = array();
		}

		$processed = 0;
		foreach ( $events as $ev ) {
			$email = isset( $ev['email'] ) ? sanitize_email( $ev['email'] ) : '';
			if ( ! $email || ! is_email( $email ) ) {
				continue;
			}
			if ( 'bounce' === $ev['event'] ) {
				SEMNEWS_Subscribers::record_bounce( $email, ( isset( $ev['type'] ) && 'soft' === $ev['type'] ) ? 'soft' : 'hard' );
				$processed++;
			} elseif ( 'complaint' === $ev['event'] ) {
				SEMNEWS_Subscribers::record_complaint( $email );
				$processed++;
			}
		}

		return new WP_REST_Response( array( 'ok' => true, 'processed' => $processed ), 200 );
	}

	/**
	 * Auto-detect the provider from the request shape and normalise it into a
	 * list of events: [ { event: bounce|complaint, email, type: hard|soft } ].
	 *
	 * Returns the string 'subscription_confirmed' for an SNS handshake.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|string
	 */
	protected function parse_request( $request ) {
		$raw  = $request->get_body();
		$json = json_decode( (string) $raw, true );

		// Amazon SES delivered through SNS.
		$sns = $request->get_header( 'x-amz-sns-message-type' );
		if ( $sns || ( is_array( $json ) && isset( $json['Type'], $json['TopicArn'] ) ) ) {
			return $this->parse_ses( is_array( $json ) ? $json : array() );
		}

		// SendGrid: a JSON array of event objects.
		if ( is_array( $json ) && isset( $json[0] ) && is_array( $json[0] ) ) {
			return $this->parse_sendgrid( $json );
		}

		// Mailgun (modern JSON).
		if ( is_array( $json ) && isset( $json['event-data'] ) && is_array( $json['event-data'] ) ) {
			return $this->parse_mailgun( $json['event-data'] );
		}

		// Postmark.
		if ( is_array( $json ) && isset( $json['RecordType'] ) ) {
			return $this->parse_postmark( $json );
		}

		// Mailgun legacy (form-encoded) shares event/recipient/severity keys.
		$params = is_array( $json ) ? $json : $request->get_params();
		if ( isset( $params['event'], $params['recipient'] ) ) {
			return $this->parse_mailgun( $params );
		}

		// Our own generic format { event, email, type } (+ filter for custom maps).
		$data  = apply_filters( 'semnews_webhook_payload', $params, $request );
		$event = isset( $data['event'] ) ? sanitize_key( $data['event'] ) : '';
		$emap  = array(
			'bounce'      => 'bounce',
			'hard_bounce' => 'bounce',
			'soft_bounce' => 'bounce',
			'complaint'   => 'complaint',
			'spam'        => 'complaint',
			'abuse'       => 'complaint',
		);
		if ( empty( $data['email'] ) || ! isset( $emap[ $event ] ) ) {
			return array();
		}
		$soft = ( 'soft_bounce' === $event ) || ( isset( $data['type'] ) && 'soft' === $data['type'] );
		return array(
			array(
				'event' => $emap[ $event ],
				'email' => $data['email'],
				'type'  => $soft ? 'soft' : 'hard',
			),
		);
	}

	/**
	 * SendGrid Event Webhook: an array of events.
	 *
	 * @param array $events Events.
	 * @return array
	 */
	protected function parse_sendgrid( $events ) {
		$out = array();
		foreach ( $events as $e ) {
			if ( ! is_array( $e ) || empty( $e['email'] ) ) {
				continue;
			}
			$event = isset( $e['event'] ) ? $e['event'] : '';
			if ( 'bounce' === $event ) {
				$type  = ( isset( $e['type'] ) && 'blocked' === $e['type'] ) ? 'soft' : 'hard';
				$out[] = array( 'event' => 'bounce', 'email' => $e['email'], 'type' => $type );
			} elseif ( 'dropped' === $event ) {
				$out[] = array( 'event' => 'bounce', 'email' => $e['email'], 'type' => 'hard' );
			} elseif ( 'spamreport' === $event ) {
				$out[] = array( 'event' => 'complaint', 'email' => $e['email'] );
			}
		}
		return $out;
	}

	/**
	 * Mailgun: modern event-data or legacy form fields (same keys).
	 *
	 * @param array $d Event data.
	 * @return array
	 */
	protected function parse_mailgun( $d ) {
		$event     = isset( $d['event'] ) ? $d['event'] : '';
		$recipient = isset( $d['recipient'] ) ? $d['recipient'] : '';
		$severity  = isset( $d['severity'] ) ? $d['severity'] : '';
		if ( ! $recipient ) {
			return array();
		}
		if ( in_array( $event, array( 'failed', 'bounced', 'rejected' ), true ) ) {
			return array( array( 'event' => 'bounce', 'email' => $recipient, 'type' => ( 'temporary' === $severity ) ? 'soft' : 'hard' ) );
		}
		if ( 'complained' === $event ) {
			return array( array( 'event' => 'complaint', 'email' => $recipient ) );
		}
		return array();
	}

	/**
	 * Postmark webhook.
	 *
	 * @param array $d Payload.
	 * @return array
	 */
	protected function parse_postmark( $d ) {
		$record = isset( $d['RecordType'] ) ? $d['RecordType'] : '';
		$email  = isset( $d['Email'] ) ? $d['Email'] : '';
		if ( ! $email ) {
			return array();
		}
		if ( 'SpamComplaint' === $record ) {
			return array( array( 'event' => 'complaint', 'email' => $email ) );
		}
		if ( 'Bounce' === $record ) {
			$type = isset( $d['Type'] ) ? $d['Type'] : '';
			if ( in_array( $type, array( 'SpamComplaint', 'SpamNotification' ), true ) ) {
				return array( array( 'event' => 'complaint', 'email' => $email ) );
			}
			$soft = in_array( $type, array( 'SoftBounce', 'Transient', 'DnsError', 'Blocked' ), true );
			return array( array( 'event' => 'bounce', 'email' => $email, 'type' => $soft ? 'soft' : 'hard' ) );
		}
		return array();
	}

	/**
	 * Amazon SES via SNS (notification or event publishing). Auto-confirms the
	 * SNS subscription (to genuine AWS endpoints only) and parses bounce/complaint
	 * notifications, which may list several recipients.
	 *
	 * @param array $json SNS envelope.
	 * @return array|string
	 */
	protected function parse_ses( $json ) {
		$msg_type = isset( $json['Type'] ) ? $json['Type'] : '';

		if ( 'SubscriptionConfirmation' === $msg_type && ! empty( $json['SubscribeURL'] ) ) {
			$url  = esc_url_raw( $json['SubscribeURL'] );
			$host = $url ? wp_parse_url( $url, PHP_URL_HOST ) : '';
			// SSRF guard: only auto-confirm to real AWS SNS endpoints.
			if ( $host && preg_match( '/(^|\.)amazonaws\.com$/', $host ) ) {
				wp_remote_get( $url, array( 'timeout' => 15 ) );
			}
			return 'subscription_confirmed';
		}

		if ( 'Notification' !== $msg_type || empty( $json['Message'] ) ) {
			return array();
		}

		$message = json_decode( $json['Message'], true );
		if ( ! is_array( $message ) ) {
			return array();
		}

		$kind = isset( $message['notificationType'] ) ? $message['notificationType'] : ( isset( $message['eventType'] ) ? $message['eventType'] : '' );
		$out  = array();

		if ( 'Bounce' === $kind && isset( $message['bounce']['bouncedRecipients'] ) && is_array( $message['bounce']['bouncedRecipients'] ) ) {
			$soft = isset( $message['bounce']['bounceType'] ) && 'Transient' === $message['bounce']['bounceType'];
			foreach ( $message['bounce']['bouncedRecipients'] as $r ) {
				if ( ! empty( $r['emailAddress'] ) ) {
					$out[] = array( 'event' => 'bounce', 'email' => $r['emailAddress'], 'type' => $soft ? 'soft' : 'hard' );
				}
			}
		} elseif ( 'Complaint' === $kind && isset( $message['complaint']['complainedRecipients'] ) && is_array( $message['complaint']['complainedRecipients'] ) ) {
			foreach ( $message['complaint']['complainedRecipients'] as $r ) {
				if ( ! empty( $r['emailAddress'] ) ) {
					$out[] = array( 'event' => 'complaint', 'email' => $r['emailAddress'] );
				}
			}
		}

		return $out;
	}

	/**
	 * Capture wp_mail() send-time failures for diagnostics.
	 *
	 * A send-time failure is usually a server/SMTP issue, NOT an asynchronous
	 * bounce, so we deliberately do NOT auto-suppress here — we just record the
	 * last error so the admin can see it, and fire an action for integrations.
	 *
	 * @param WP_Error $error Mail error.
	 * @return void
	 */
	public function on_mail_failed( $error ) {
		if ( is_wp_error( $error ) ) {
			set_transient( 'semnews_last_mail_error', $error->get_error_message(), DAY_IN_SECONDS );
			/**
			 * Fires when wp_mail() reports a send-time failure.
			 *
			 * @param WP_Error $error Mail error.
			 */
			do_action( 'semnews_mail_send_failed', $error );
		}
	}
}
