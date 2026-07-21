<?php
/**
 * Main plugin orchestrator.
 *
 * @package QuintessentialNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton that loads components and registers shared hooks.
 */
class SEMNEWS_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var SEMNEWS_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Forms controller.
	 *
	 * @var SEMNEWS_Forms
	 */
	public $forms;

	/**
	 * Opt-in controller.
	 *
	 * @var SEMNEWS_Optin
	 */
	public $optin;

	/**
	 * Sender.
	 *
	 * @var SEMNEWS_Sender
	 */
	public $sender;

	/**
	 * GDPR controller.
	 *
	 * @var SEMNEWS_GDPR
	 */
	public $gdpr;

	/**
	 * Admin controller (admin only).
	 *
	 * @var SEMNEWS_Admin|null
	 */
	public $admin = null;

	/**
	 * Get / boot the singleton.
	 *
	 * @return SEMNEWS_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Wire everything up.
	 *
	 * @return void
	 */
	private function boot() {
		// Translations load automatically (just-in-time since WP 4.6); the
		// plugin ships a .pot only, so no manual load_plugin_textdomain().

		// Run in-place DB upgrades if needed.
		SEMNEWS_Install::maybe_upgrade();

		// Components.
		$this->forms = new SEMNEWS_Forms();
		$this->forms->init();

		( new SEMNEWS_Display() )->init();

		$this->optin = new SEMNEWS_Optin();
		$this->optin->init();

		$this->sender = new SEMNEWS_Sender();
		$this->sender->init();

		( new SEMNEWS_Webhook() )->init();

		$this->gdpr = new SEMNEWS_GDPR();
		$this->gdpr->init();

		// Daily queue hygiene: purge rows of long-finished campaigns, reap stale
		// claims and finalize campaigns stuck in "sending" with nothing left.
		add_action( SEMNEWS_Install::CRON_CLEANUP, array( 'SEMNEWS_Queue', 'daily_maintenance' ) );

		( new SEMNEWS_Block() )->init();

		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		// Front-end assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );

		if ( is_admin() && class_exists( 'SEMNEWS_Admin' ) ) {
			$this->admin = new SEMNEWS_Admin();
			$this->admin->init();
		}

		do_action( 'semnews_loaded', $this );
	}

	/**
	 * Register the classic widget.
	 *
	 * @return void
	 */
	public function register_widget() {
		register_widget( 'SEMNEWS_Widget' );
	}

	/**
	 * Enqueue front-end CSS/JS. JS is only needed for AJAX form submission.
	 *
	 * @return void
	 */
	public function enqueue_public_assets() {
		wp_enqueue_style( 'semnews-public', SEMNEWS_PLUGIN_URL . 'assets/css/public.css', array(), SEMNEWS_VERSION );
		// Load assets/css/public-rtl.css automatically on RTL locales.
		wp_style_add_data( 'semnews-public', 'rtl', 'replace' );

		wp_enqueue_script( 'semnews-public', SEMNEWS_PLUGIN_URL . 'assets/js/public.js', array(), SEMNEWS_VERSION, true );
		wp_localize_script(
			'semnews-public',
			'semnewsPublic',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}
}
