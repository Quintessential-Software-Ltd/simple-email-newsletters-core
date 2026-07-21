<?php
/**
 * Uninstall handler.
 *
 * By default we KEEP all subscriber data — deleting people's data should be a
 * deliberate choice, never an accident of removing a plugin. Data is only
 * dropped when the site owner explicitly enabled "delete data on uninstall".
 * Even then, the one-way suppression salt/list is preserved so previously
 * unsubscribed/erased people stay protected if the plugin is reinstalled.
 *
 * On multisite this runs the same cleanup on every site, so no orphaned
 * wp_N_semnews_* tables are left behind on subsites. Legacy "sen_"- and
 * "senews_"-prefixed names (from before the prefix renames) are cleaned up
 * alongside the current "semnews_" ones, in case the plugin is removed without
 * the rename migration ever running.
 *
 * @package QuintessentialNewsletters
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up the CURRENT site (honouring its own delete-data setting).
 *
 * @return void
 */
function semnews_uninstall_current_site() {
	global $wpdb;

	// Cron + transients are removed regardless — they are runtime state, not data.
	foreach ( array( 'semnews_', 'senews_', 'sen_' ) as $semnews_prefix ) {
		wp_clear_scheduled_hook( $semnews_prefix . 'process_queue' );
		wp_clear_scheduled_hook( $semnews_prefix . 'daily_cleanup' );
		wp_clear_scheduled_hook( $semnews_prefix . 'license_recheck' );
		wp_clear_scheduled_hook( $semnews_prefix . 'automation_tick' );
		delete_transient( $semnews_prefix . 'license_ok' );
		delete_transient( $semnews_prefix . 'last_mail_error' );
		delete_transient( $semnews_prefix . 'activation_redirect' );
		delete_transient( $semnews_prefix . 'update_manifest' );
	}

	// The Pro add-on's cron hooks carry their own prefix (current and legacy).
	wp_clear_scheduled_hook( 'semnewsp_license_recheck' );
	wp_clear_scheduled_hook( 'senp_license_recheck' );
	wp_clear_scheduled_hook( 'semnewsp_engagement_purge' );
	wp_clear_scheduled_hook( 'senp_engagement_purge' );

	// Deliverability report transients are keyed by a domain hash; clear via LIKE.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_semnews\_deliverability\_%' OR option_name LIKE '\_transient\_timeout\_semnews\_deliverability\_%' OR option_name LIKE '\_transient\_senews\_deliverability\_%' OR option_name LIKE '\_transient\_timeout\_senews\_deliverability\_%' OR option_name LIKE '\_transient\_sen\_deliverability\_%' OR option_name LIKE '\_transient\_timeout\_sen\_deliverability\_%'" );

	$settings = get_option( 'semnews_settings', null );
	if ( null === $settings ) {
		$settings = get_option( 'senews_settings', null ); // Pre-rename install that never migrated.
	}
	if ( null === $settings ) {
		$settings = get_option( 'sen_settings', array() ); // Pre-2.5.0 install that never migrated.
	}
	$delete_all = is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] );

	if ( ! $delete_all ) {
		return;
	}

	// Drop custom tables (keep suppression so erased/unsubscribed people stay safe).
	$tables = array();
	foreach ( array( 'semnews_', 'senews_', 'sen_' ) as $semnews_prefix ) {
		$tables[] = $wpdb->prefix . $semnews_prefix . 'subscribers';
		$tables[] = $wpdb->prefix . $semnews_prefix . 'campaigns';
		$tables[] = $wpdb->prefix . $semnews_prefix . 'queue';
		$tables[] = $wpdb->prefix . $semnews_prefix . 'consent_log';
	}

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- uninstall cleanup dropping this plugin's own tables; names are fixed literals on $wpdb->prefix, escaped anyway.
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
	}

	// Remove options (but not the suppression salt/list).
	foreach ( array( 'semnews_', 'senews_', 'sen_' ) as $semnews_prefix ) {
		delete_option( $semnews_prefix . 'settings' );
		delete_option( $semnews_prefix . 'db_version' );
		delete_option( $semnews_prefix . 'license_status' );
		delete_option( $semnews_prefix . 'license_token' );
		delete_option( $semnews_prefix . 'license_ls' );
		delete_option( $semnews_prefix . 'license_last_check' );
		delete_option( $semnews_prefix . 'automation' );
		delete_option( $semnews_prefix . 'display' );
		delete_option( $semnews_prefix . 'senders' );
		delete_option( $semnews_prefix . 'setup_done' );
		delete_option( $semnews_prefix . 'webhook_secret' );

		// Per-user UI preferences.
		delete_metadata( 'user', 0, $semnews_prefix . 'build_prefs', '', true );
	}
	delete_option( 'semnewsp_license_key' ); // Pro add-on key (cleaned here when the whole suite is removed).
	delete_option( 'senp_license_key' ); // Its pre-rename location.
}

if ( is_multisite() ) {
	$semnews_site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $semnews_site_ids as $semnews_site_id ) {
		switch_to_blog( (int) $semnews_site_id );
		semnews_uninstall_current_site();
		restore_current_blog();
	}
} else {
	semnews_uninstall_current_site();
}
