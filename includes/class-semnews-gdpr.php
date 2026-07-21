<?php
/**
 * GDPR integration.
 *
 * Hooks into WordPress' built-in privacy tools so a site admin can fulfil
 * data subject access (Art. 15 / Art. 20) and erasure (Art. 17) requests from
 * Tools → Export/Erase Personal Data. Also registers suggested privacy-policy
 * text and a daily job that purges stale, unconfirmed signups (data
 * minimisation / storage limitation, Art. 5(1)(c)/(e)).
 *
 * @package SimpleEmailNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This class reads and writes the plugin's own custom tables. Every
// interpolated table name is built from $wpdb->prefix plus a fixed literal
// (never user input), all values go through $wpdb->prepare(), and the WP
// post/meta APIs and object cache do not apply to these tables: queue,
// consent and subscriber state must always read current.

/**
 * GDPR controller.
 */
class SEMNEWS_GDPR {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_action( SEMNEWS_Install::CRON_CLEANUP, array( $this, 'purge_stale_pending' ) );
	}

	/**
	 * Register the personal data exporter.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['quintessential-newsletters'] = array(
			'exporter_friendly_name' => __( 'Newsletter subscription', 'quintessential-newsletters' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the personal data eraser.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['quintessential-newsletters'] = array(
			'eraser_friendly_name' => __( 'Newsletter subscription', 'quintessential-newsletters' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export a subscriber's data by email.
	 *
	 * @param string $email_address Requested email.
	 * @param int    $page          Pagination (unused; one subscriber per email).
	 * @return array
	 */
	public function export( $email_address, $page = 1 ) {
		$subscriber = SEMNEWS_Subscribers::get_by_email( $email_address );
		$export     = array();

		if ( $subscriber ) {
			$data = array(
				array(
					'name'  => __( 'Email', 'quintessential-newsletters' ),
					'value' => $subscriber->email,
				),
				array(
					'name'  => __( 'Name', 'quintessential-newsletters' ),
					'value' => $subscriber->name,
				),
				array(
					'name'  => __( 'Status', 'quintessential-newsletters' ),
					'value' => $subscriber->status,
				),
				array(
					'name'  => __( 'Consent text agreed to', 'quintessential-newsletters' ),
					'value' => $subscriber->consent_text,
				),
				array(
					'name'  => __( 'Signup IP', 'quintessential-newsletters' ),
					'value' => $subscriber->ip_signup,
				),
				array(
					'name'  => __( 'Confirmation IP', 'quintessential-newsletters' ),
					'value' => $subscriber->ip_confirmed,
				),
				array(
					'name'  => __( 'Subscribed at', 'quintessential-newsletters' ),
					'value' => $subscriber->created_at,
				),
				array(
					'name'  => __( 'Confirmed at', 'quintessential-newsletters' ),
					'value' => $subscriber->confirmed_at,
				),
				array(
					'name'  => __( 'Source', 'quintessential-newsletters' ),
					'value' => $subscriber->source,
				),
			);

			$export[] = array(
				'group_id'    => 'semnews_subscriber',
				'group_label' => __( 'Newsletter subscription', 'quintessential-newsletters' ),
				'item_id'     => 'semnews-subscriber-' . $subscriber->id,
				'data'        => $data,
			);

			// Consent log entries.
			$log = SEMNEWS_Consent_Log::get_for_subscriber( $subscriber->id );
			foreach ( $log as $entry ) {
				$export[] = array(
					'group_id'    => 'semnews_consent_log',
					'group_label' => __( 'Newsletter consent history', 'quintessential-newsletters' ),
					'item_id'     => 'semnews-consent-' . $entry['id'],
					'data'        => array(
						array(
							'name'  => __( 'Event', 'quintessential-newsletters' ),
							'value' => $entry['event'],
						),
						array(
							'name'  => __( 'Date', 'quintessential-newsletters' ),
							'value' => $entry['created_at'],
						),
						array(
							'name'  => __( 'IP', 'quintessential-newsletters' ),
							'value' => $entry['ip'],
						),
						array(
							'name'  => __( 'Consent text', 'quintessential-newsletters' ),
							'value' => $entry['consent_text'],
						),
					),
				);
			}
		}

		return array(
			'data' => $export,
			'done' => true,
		);
	}

	/**
	 * Erase a subscriber's data by email.
	 *
	 * @param string $email_address Requested email.
	 * @param int    $page          Pagination (unused).
	 * @return array
	 */
	public function erase( $email_address, $page = 1 ) {
		$subscriber = SEMNEWS_Subscribers::get_by_email( $email_address );
		$removed    = false;
		$messages   = array();

		if ( $subscriber ) {
			// Erase the PII but keep a one-way suppression hash so the address is
			// not silently re-imported later (GDPR Art. 17 with re-mail safety).
			SEMNEWS_Subscribers::delete( $subscriber->id, SEMNEWS_Suppression::REASON_ERASURE );
			$removed    = true;
			$messages[] = __( 'Newsletter subscription and consent history deleted.', 'quintessential-newsletters' );
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Suggested privacy policy content for the site's policy page.
	 *
	 * @return void
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<p>%s</p><p>%s</p>',
			esc_html__( 'When you subscribe to our newsletter we store your email address, your name (if provided), the date and time you subscribed and confirmed, the IP address you subscribed from, and the consent wording you agreed to. We keep this so we can send you the newsletter and prove that you opted in.', 'quintessential-newsletters' ),
			esc_html__( 'We use double opt-in: you must click a confirmation link before you receive anything. You can unsubscribe at any time using the link in every email, and you can ask us to export or delete your data at any time.', 'quintessential-newsletters' )
		);

		wp_add_privacy_policy_content( __( 'Simple Email Newsletters', 'quintessential-newsletters' ), $content );
	}

	/**
	 * Delete unconfirmed signups older than the configured retention window.
	 *
	 * @return void
	 */
	public function purge_stale_pending() {
		global $wpdb;

		$days = (int) semnews_get_option( 'retention_days', 30 );
		if ( $days <= 0 ) {
			return; // Retention disabled.
		}

		$table  = SEMNEWS_Install::table( 'subscribers' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		/**
		 * Cap on stale pending signups purged per daily run, so a bot-flood of
		 * pending rows cannot turn the cleanup cron into a runaway query storm.
		 * The remainder is picked up on subsequent days.
		 *
		 * @param int $limit Max rows per run.
		 */
		$limit = max( 1, (int) apply_filters( 'semnews_purge_batch_limit', 500 ) );

		// Collect a bounded set of IDs so we can also clean their consent logs.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE status = %s AND created_at < %s ORDER BY id ASC LIMIT %d",
				SEMNEWS_Subscribers::STATUS_PENDING,
				$cutoff,
				$limit
			)
		);

		foreach ( $ids as $id ) {
			SEMNEWS_Subscribers::delete( (int) $id );
		}
	}
}
