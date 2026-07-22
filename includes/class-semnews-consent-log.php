<?php
/**
 * Consent / audit log.
 *
 * Stores an immutable-ish trail of consent events so the site owner can prove,
 * for any subscriber, exactly what they agreed to, when, and from where
 * (GDPR Art. 7(1) — the controller must be able to demonstrate consent).
 *
 * @package QuintessentialNewsletters
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
 * Consent log data access.
 */
class SEMNEWS_Consent_Log {

	/**
	 * Record a consent-related event.
	 *
	 * @param int   $subscriber_id Subscriber ID.
	 * @param string $event        Event key: subscribe_request|confirm|unsubscribe|resubscribe|import|admin_add.
	 * @param array $data          Optional: email, consent_text, source, ip, user_agent.
	 * @return int|false Insert ID or false.
	 */
	public static function add( $subscriber_id, $event, $data = array() ) {
		global $wpdb;

		$row = array(
			'subscriber_id' => absint( $subscriber_id ),
			'email'         => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
			'event'         => substr( sanitize_key( $event ), 0, 40 ),
			'consent_text'  => isset( $data['consent_text'] ) ? wp_kses_post( $data['consent_text'] ) : null,
			'source'        => isset( $data['source'] ) ? substr( sanitize_text_field( $data['source'] ), 0, 120 ) : null,
			'ip'            => isset( $data['ip'] ) ? substr( sanitize_text_field( $data['ip'] ), 0, 45 ) : null,
			'user_agent'    => isset( $data['user_agent'] ) ? substr( sanitize_text_field( $data['user_agent'] ), 0, 255 ) : null,
			'created_at'    => current_time( 'mysql', true ),
		);

		$result = $wpdb->insert( SEMNEWS_Install::table( 'consent_log' ), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Fetch the consent history for a subscriber, newest first.
	 *
	 * @param int $subscriber_id Subscriber ID.
	 * @return array
	 */
	public static function get_for_subscriber( $subscriber_id ) {
		global $wpdb;

		$table = SEMNEWS_Install::table( 'consent_log' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE subscriber_id = %d ORDER BY created_at DESC, id DESC",
				absint( $subscriber_id )
			),
			ARRAY_A
		);
	}

	/**
	 * One page of the full consent log, oldest first (for the Art. 30 export).
	 *
	 * @param int $per_page Rows per page.
	 * @param int $offset   Offset.
	 * @return array
	 */
	public static function page( $per_page, $offset ) {
		global $wpdb;
		$table = SEMNEWS_Install::table( 'consent_log' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
				max( 1, (int) $per_page ),
				max( 0, (int) $offset )
			),
			ARRAY_A
		);
	}

	/**
	 * Delete all log rows for a subscriber (used by the GDPR eraser).
	 *
	 * @param int $subscriber_id Subscriber ID.
	 * @return void
	 */
	public static function delete_for_subscriber( $subscriber_id ) {
		global $wpdb;
		$wpdb->delete( SEMNEWS_Install::table( 'consent_log' ), array( 'subscriber_id' => absint( $subscriber_id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
