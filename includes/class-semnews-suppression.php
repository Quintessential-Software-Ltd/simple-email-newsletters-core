<?php
/**
 * Suppression list.
 *
 * When someone unsubscribes, is erased (GDPR Art. 17), or hard-bounces, we keep
 * a one-way salted hash of their email — never the plaintext after erasure — so
 * they can never be silently re-mailed or re-imported from a CSV. A person can
 * always opt back in themselves with fresh consent (which clears their hash);
 * the suppression list only stops the *site owner* from re-adding them.
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
 * Suppression data access.
 */
class SEMNEWS_Suppression {

	const REASON_UNSUBSCRIBE = 'unsubscribe';
	const REASON_BOUNCE      = 'bounce';
	const REASON_COMPLAINT   = 'complaint';
	const REASON_ERASURE     = 'erasure';

	/**
	 * Stable one-way hash of an email address.
	 *
	 * Uses a dedicated, per-site salt stored once at install so hashes survive
	 * even if WordPress' own salts are rotated.
	 *
	 * @param string $email Email address.
	 * @return string 64-char hex hash.
	 */
	public static function hash( $email ) {
		$salt = get_option( 'semnews_suppression_salt' );
		if ( ! $salt ) {
			$salt = wp_generate_password( 64, true, true );
			update_option( 'semnews_suppression_salt', $salt, false );
		}
		return hash_hmac( 'sha256', strtolower( trim( $email ) ), $salt );
	}

	/**
	 * Add an address to the suppression list (idempotent).
	 *
	 * @param string $email  Email address.
	 * @param string $reason One of the REASON_* constants.
	 * @return void
	 */
	public static function add( $email, $reason ) {
		global $wpdb;

		if ( ! is_email( $email ) ) {
			return;
		}

		$hash = self::hash( $email );

		// Already present? Leave the original reason/timestamp intact.
		if ( self::is_suppressed( $email ) ) {
			return;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			SEMNEWS_Install::table( 'suppression' ),
			array(
				'email_hash' => $hash,
				'reason'     => substr( sanitize_key( $reason ), 0, 20 ),
				'created_at' => current_time( 'mysql', true ),
			)
		);

		do_action( 'semnews_subscriber_suppressed', $hash, $reason );
	}

	/**
	 * Whether an address is currently suppressed.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public static function is_suppressed( $email ) {
		global $wpdb;

		if ( ! is_email( $email ) ) {
			return false;
		}

		$table = SEMNEWS_Install::table( 'suppression' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE email_hash = %s", self::hash( $email ) ) );

		return ! empty( $found );
	}

	/**
	 * Remove an address from the suppression list.
	 *
	 * Called when a person genuinely re-opts-in with fresh consent — their own
	 * action overrides a prior suppression.
	 *
	 * @param string $email Email address.
	 * @return void
	 */
	public static function remove( $email ) {
		global $wpdb;

		if ( ! is_email( $email ) ) {
			return;
		}

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			SEMNEWS_Install::table( 'suppression' ),
			array( 'email_hash' => self::hash( $email ) ),
			array( '%s' )
		);
	}

	/**
	 * Total number of suppressed addresses.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		$table = SEMNEWS_Install::table( 'suppression' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}
}
