<?php
/**
 * Plugin Name:       Quintessential Newsletters
 * Plugin URI:        https://github.com/Quintessential-Software-Ltd/quintessential-newsletters-core
 * Description:       Honest, GDPR-friendly newsletters for WordPress. Double opt-in and consent logging as standard. Unlimited subscribers, free.
 * Version:           2.5.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Quintessential Software Ltd
 * Author URI:        https://quintessentialsoftware.co.uk
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       quintessential-newsletters
 * Domain Path:       /languages
 *
 * @package QuintessentialNewsletters
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Constants.
// ---------------------------------------------------------------------------

define( 'SEMNEWS_VERSION', '2.5.2' );

/**
 * Database schema version. Bumped whenever the table structure changes (or a
 * data migration must run) so the installer picks it up on upgrade.
 */
define( 'SEMNEWS_DB_VERSION', '1.5.0' );

define( 'SEMNEWS_PLUGIN_FILE', __FILE__ );
define( 'SEMNEWS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEMNEWS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SEMNEWS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ---------------------------------------------------------------------------
// Autoloader (safety net).
// ---------------------------------------------------------------------------

/**
 * Resolve a SEMNEWS_* class to its file if an explicit require below ever misses
 * it. The manual requires stay (they fix load order and run functions.php),
 * but this guarantees a referenced class can never fatal with "class not
 * found" — it is loaded on demand from includes/ or admin/ instead.
 *
 * @param string $class Class name being autoloaded.
 * @return void
 */
spl_autoload_register(
	function ( $class ) {
		if ( 0 !== strpos( $class, 'SEMNEWS_' ) ) {
			return;
		}
		$file = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
		foreach ( array( 'includes/', 'admin/' ) as $dir ) {
			$path = SEMNEWS_PLUGIN_DIR . $dir . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
);

// ---------------------------------------------------------------------------
// Includes.
// ---------------------------------------------------------------------------

require_once SEMNEWS_PLUGIN_DIR . 'includes/functions.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-install.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-consent-log.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-suppression.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-subscribers.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-campaigns.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-queue.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-mailer.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-optin.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-forms.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-display.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-templates.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-linter.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-deliverability.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-sender.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-webhook.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-gdpr.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-block.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-widget.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-plugin.php';

if ( is_admin() ) {
	require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-settings.php';
	require_once SEMNEWS_PLUGIN_DIR . 'admin/class-semnews-admin.php';
}

// Register WP-CLI commands when running under WP-CLI.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-cli.php';
	WP_CLI::add_command( 'semnews subscriber export', array( 'SEMNEWS_CLI', 'subscriber_export' ) );
	WP_CLI::add_command( 'semnews subscriber list', array( 'SEMNEWS_CLI', 'subscriber_list' ) );
	WP_CLI::add_command( 'semnews subscriber add', array( 'SEMNEWS_CLI', 'subscriber_add' ) );
	WP_CLI::add_command( 'semnews queue run', array( 'SEMNEWS_CLI', 'queue_run' ) );
	WP_CLI::add_command( 'semnews bounce', array( 'SEMNEWS_CLI', 'bounce' ) );
	WP_CLI::add_command( 'semnews complaint', array( 'SEMNEWS_CLI', 'complaint' ) );
	WP_CLI::add_command( 'semnews deliverability', array( 'SEMNEWS_CLI', 'deliverability' ) );
}

// ---------------------------------------------------------------------------
// Activation / deactivation / uninstall.
// ---------------------------------------------------------------------------

register_activation_hook( __FILE__, array( 'SEMNEWS_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SEMNEWS_Install', 'deactivate' ) );
// Uninstall is handled by uninstall.php.

// Register the custom cron interval at load time so it exists during activation.
add_filter( 'cron_schedules', array( 'SEMNEWS_Install', 'cron_schedules' ) );

// Multisite: set up newly created subsites when the plugin is network-active.
add_action( 'wp_initialize_site', array( 'SEMNEWS_Install', 'initialize_new_site' ), 20 );

// ---------------------------------------------------------------------------
// Bootstrap.
// ---------------------------------------------------------------------------

/**
 * Main plugin instance accessor.
 *
 * @return SEMNEWS_Plugin
 */
function semnews() {
	return SEMNEWS_Plugin::instance();
}

/**
 * Boot the plugin, containing any startup fatal so a bug degrades to an
 * admin notice instead of a site-wide critical error. The rest of WordPress
 * keeps loading either way.
 *
 * @return void
 */
function semnews_boot() {
	try {
		semnews();
	} catch ( \Throwable $e ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- surfacing a genuine boot failure to the server log.
		error_log( 'Quintessential Newsletters could not start: ' . $e->getMessage() );
		add_action(
			'admin_notices',
			function () use ( $e ) {
				echo '<div class="notice notice-error"><p><strong>';
				esc_html_e( 'Quintessential Newsletters could not start.', 'quintessential-newsletters' );
				echo '</strong> ';
				echo esc_html( $e->getMessage() );
				echo '</p></div>';
			}
		);
	}
}

// Kick everything off once all plugins are loaded.
add_action( 'plugins_loaded', 'semnews_boot' );
