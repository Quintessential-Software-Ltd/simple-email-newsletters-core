<?php
/**
 * Installation, upgrades and lifecycle hooks.
 *
 * @package SimpleEmailNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database table creation, default options, cron scheduling and cleanup.
 */
class SEMNEWS_Install {

	/**
	 * Cron hook that processes the send queue in batches.
	 */
	const CRON_PROCESS_QUEUE = 'semnews_process_queue';

	/**
	 * Cron hook that purges stale, unconfirmed signups (GDPR data minimisation).
	 */
	const CRON_CLEANUP = 'semnews_daily_cleanup';

	/**
	 * Runs on plugin activation. Network activation on multisite sets up every
	 * existing site, not just the main one.
	 *
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_single();
				restore_current_blog();
			}
			return;
		}

		self::activate_single();
		set_transient( 'semnews_activation_redirect', 1, 30 );
	}

	/**
	 * Per-site activation work.
	 *
	 * @return void
	 */
	protected static function activate_single() {
		self::maybe_migrate_legacy_prefix();
		self::create_tables();
		self::set_default_options();
		self::schedule_events();

		// Opt-in links use a query-var endpoint, no rewrite rules required, so
		// no flush is needed. We store the DB version for future migrations.
		update_option( 'semnews_db_version', SEMNEWS_DB_VERSION );
	}

	/**
	 * Set up a newly created subsite when the plugin is network-active
	 * (hooked to wp_initialize_site).
	 *
	 * @param WP_Site $new_site The new site object.
	 * @return void
	 */
	public static function initialize_new_site( $new_site ) {
		if ( ! is_multisite() ) {
			return;
		}

		$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
		if ( ! isset( $network_plugins[ SEMNEWS_PLUGIN_BASENAME ] ) ) {
			return;
		}

		switch_to_blog( (int) $new_site->blog_id );
		self::activate_single();
		restore_current_blog();
	}

	/**
	 * Runs on plugin deactivation. We clear scheduled events but keep all data.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_PROCESS_QUEUE );
		wp_clear_scheduled_hook( self::CRON_CLEANUP );
		// The digest tick belongs to the Pro add-on now, but clear it anyway so
		// a site that removes the suite is left with no orphaned event.
		wp_clear_scheduled_hook( 'semnews_automation_tick' );
	}

	/**
	 * Run table creation / migration if the stored DB version is behind.
	 * Called on every load so upgrades-in-place are handled without reactivation.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		self::maybe_migrate_legacy_prefix();

		if ( get_option( 'semnews_db_version' ) !== SEMNEWS_DB_VERSION ) {
			self::create_tables();
			// Also seed defaults + secrets: on multisite a subsite may reach this
			// lazy-upgrade path without ever running the activation hook.
			self::set_default_options();
			self::schedule_events();
			update_option( 'semnews_db_version', SEMNEWS_DB_VERSION );
		}
	}

	/**
	 * One-time prefix migration. The plugin's identifier prefix has changed
	 * across releases: "sen" -> "senews" (WordPress.org requires prefixes
	 * longer than four characters) -> "qnews" -> "semnews". Rename the existing
	 * tables and options in place so nobody's subscribers, campaigns, settings
	 * or secrets are lost on upgrade, whichever era the site comes from.
	 *
	 * Idempotent: it only runs while an old *_db_version option exists and the
	 * new one does not.
	 *
	 * @return void
	 */
	protected static function maybe_migrate_legacy_prefix() {
		global $wpdb;

		if ( false !== get_option( 'semnews_db_version' ) ) {
			return;
		}

		// Most recent era first: each prefix change absorbed any older data, so
		// only one legacy prefix ever holds the live data at a time.
		if ( false !== get_option( 'qnews_db_version' ) ) {
			$legacy = 'qnews';
		} elseif ( false !== get_option( 'senews_db_version' ) ) {
			$legacy = 'senews';
		} elseif ( false !== get_option( 'sen_db_version' ) ) {
			$legacy = 'sen';
		} else {
			return;
		}

		// Tables: rename in place (never clobber a new table that already exists).
		foreach ( array( 'subscribers', 'campaigns', 'queue', 'consent_log', 'suppression' ) as $name ) {
			$old = $wpdb->prefix . $legacy . '_' . $name;
			$new = self::table( $name );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery -- schema migration.
			$old_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old ) );
			$new_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new ) );

			if ( $old_exists && ! $new_exists ) {
				$wpdb->query( 'RENAME TABLE `' . esc_sql( $old ) . '` TO `' . esc_sql( $new ) . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed literals from $wpdb->prefix, escaped anyway.
			}
			// phpcs:enable WordPress.DB.DirectDatabaseQuery
		}

		// Options: base name (shared by every era's prefix) => autoload.
		$options = array(
			'settings'           => true,
			'automation'         => true,
			'display'            => true,
			'setup_done'         => true,
			'suppression_salt'   => false,
			'webhook_secret'     => false,
			// Pro add-on state, migrated here so a paired Pro upgrade keeps working.
			'senders'            => true,
			'license_status'     => true,
			'license_token'      => false,
			'license_ls'         => true,
			'license_last_check' => true,
			'db_version'         => true,
		);

		$renames = array();
		foreach ( $options as $base => $autoload ) {
			$renames[ $legacy . '_' . $base ] = array( 'semnews_' . $base, $autoload );
		}

		// The Pro add-on's own key option. In the "senews" era it was
		// "senp_license_key"; in the "qnews" era it was "qnp_license_key".
		// Older than that, the key lived inside the core settings (migrated above).
		if ( 'senews' === $legacy ) {
			$renames['senp_license_key'] = array( 'semnewsp_license_key', true );
		} elseif ( 'qnews' === $legacy ) {
			$renames['qnp_license_key'] = array( 'semnewsp_license_key', true );
		}

		foreach ( $renames as $old_name => $target ) {
			list( $new_name, $autoload ) = $target;

			$value = get_option( $old_name, null );
			if ( null === $value ) {
				continue;
			}

			if ( false === get_option( $new_name, false ) ) {
				add_option( $new_name, $value, '', $autoload ? 'yes' : 'no' );
			}
			delete_option( $old_name );
		}

		// Old cron hooks: clear them; schedule_events() re-adds the new ones.
		wp_clear_scheduled_hook( $legacy . '_process_queue' );
		wp_clear_scheduled_hook( $legacy . '_daily_cleanup' );
		wp_clear_scheduled_hook( $legacy . '_automation_tick' );
		if ( 'senews' === $legacy ) {
			wp_clear_scheduled_hook( 'senp_license_recheck' );
			wp_clear_scheduled_hook( 'senp_engagement_purge' );
		} elseif ( 'qnews' === $legacy ) {
			wp_clear_scheduled_hook( 'qnp_license_recheck' );
			wp_clear_scheduled_hook( 'qnp_engagement_purge' );
		}
	}

	/**
	 * Fully-qualified table name for one of our tables.
	 *
	 * @param string $name Short name: subscribers|campaigns|queue|consent_log.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'semnews_' . $name;
	}

	/**
	 * Create or update the custom tables using dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$subscribers     = self::table( 'subscribers' );
		$campaigns       = self::table( 'campaigns' );
		$queue           = self::table( 'queue' );
		$consent_log     = self::table( 'consent_log' );
		$suppression     = self::table( 'suppression' );

		// Subscribers.
		$sql = "CREATE TABLE {$subscribers} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(191) NOT NULL,
			name VARCHAR(191) NULL DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			token VARCHAR(64) NOT NULL DEFAULT '',
			consent_text TEXT NULL,
			consent_version VARCHAR(20) NULL DEFAULT NULL,
			consent_basis VARCHAR(20) NOT NULL DEFAULT 'consent',
			bounce_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
			source VARCHAR(120) NULL DEFAULT NULL,
			ip_signup VARCHAR(45) NULL DEFAULT NULL,
			ip_confirmed VARCHAR(45) NULL DEFAULT NULL,
			created_at DATETIME NOT NULL,
			confirmed_at DATETIME NULL DEFAULT NULL,
			unsubscribed_at DATETIME NULL DEFAULT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY status (status),
			KEY token (token),
			KEY created_at (created_at),
			KEY confirmed_at (confirmed_at)
		) {$charset_collate};";
		dbDelta( $sql );

		// Campaigns.
		$sql = "CREATE TABLE {$campaigns} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			subject VARCHAR(255) NOT NULL DEFAULT '',
			preheader VARCHAR(255) NULL DEFAULT NULL,
			body LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			type VARCHAR(20) NOT NULL DEFAULT 'manual',
			template VARCHAR(50) NULL DEFAULT NULL,
			sender_id VARCHAR(40) NULL DEFAULT NULL,
			author_id BIGINT UNSIGNED NULL DEFAULT NULL,
			total_recipients INT UNSIGNED NOT NULL DEFAULT 0,
			sent_count INT UNSIGNED NOT NULL DEFAULT 0,
			failed_count INT UNSIGNED NOT NULL DEFAULT 0,
			scheduled_at DATETIME NULL DEFAULT NULL,
			sent_at DATETIME NULL DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );

		// Send queue.
		$sql = "CREATE TABLE {$queue} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT UNSIGNED NOT NULL,
			subscriber_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
			claim_id VARCHAR(32) NULL DEFAULT NULL,
			claimed_at DATETIME NULL DEFAULT NULL,
			next_attempt_at DATETIME NULL DEFAULT NULL,
			last_error VARCHAR(255) NULL DEFAULT NULL,
			sent_at DATETIME NULL DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY campaign_status (campaign_id, status),
			KEY status_attempts (status, attempts),
			KEY claim_id (claim_id),
			KEY subscriber_id (subscriber_id)
		) {$charset_collate};";
		dbDelta( $sql );

		// Consent / audit log (proof of consent, GDPR Art. 7(1)).
		$sql = "CREATE TABLE {$consent_log} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			subscriber_id BIGINT UNSIGNED NOT NULL,
			email VARCHAR(191) NOT NULL DEFAULT '',
			event VARCHAR(40) NOT NULL,
			consent_text TEXT NULL,
			source VARCHAR(120) NULL DEFAULT NULL,
			ip VARCHAR(45) NULL DEFAULT NULL,
			user_agent VARCHAR(255) NULL DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY subscriber_id (subscriber_id),
			KEY event (event)
		) {$charset_collate};";
		dbDelta( $sql );

		// Suppression list (one-way hashes of people who must not be re-mailed).
		$sql = "CREATE TABLE {$suppression} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email_hash CHAR(64) NOT NULL,
			reason VARCHAR(20) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email_hash (email_hash),
			KEY reason (reason)
		) {$charset_collate};";
		dbDelta( $sql );
	}

	/**
	 * Seed default settings without clobbering existing ones.
	 *
	 * @return void
	 */
	public static function set_default_options() {
		$existing = get_option( 'semnews_settings', null );
		if ( null === $existing ) {
			add_option( 'semnews_settings', semnews_default_settings() );
		}

		// Stable salt for one-way suppression hashes (created once, never rotated).
		if ( ! get_option( 'semnews_suppression_salt' ) ) {
			add_option( 'semnews_suppression_salt', wp_generate_password( 64, true, true ), '', false );
		}

		// Shared secret for the inbound bounce/complaint webhook (created once).
		if ( ! get_option( 'semnews_webhook_secret' ) ) {
			add_option( 'semnews_webhook_secret', wp_generate_password( 40, false, false ), '', false );
		}
	}

	/**
	 * Register our custom cron interval. Hooked at file-load time (not inside the
	 * plugin bootstrap) so the schedule also exists during the activation request,
	 * when wp_schedule_event() needs it.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		$schedules['semnews_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes (Simple Email Newsletters)', 'quintessential-newsletters' ),
		);
		return $schedules;
	}

	/**
	 * Schedule recurring cron events.
	 *
	 * @return void
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( self::CRON_PROCESS_QUEUE ) ) {
			// Every 5 minutes; relies on the custom schedule registered in SEMNEWS_Plugin.
			wp_schedule_event( time() + 60, 'semnews_five_minutes', self::CRON_PROCESS_QUEUE );
		}
		if ( ! wp_next_scheduled( self::CRON_CLEANUP ) ) {
			wp_schedule_event( time() + 120, 'daily', self::CRON_CLEANUP );
		}
	}
}
