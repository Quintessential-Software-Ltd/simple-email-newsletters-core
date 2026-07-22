<?php
/**
 * Admin controller: menus, screens, asset loading and action handlers.
 *
 * @package QuintessentialNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin controller.
 */
class SEMNEWS_Admin {

	/**
	 * Capability required to manage the newsletter.
	 *
	 * @return string
	 */
	public static function capability() {
		return apply_filters( 'semnews_admin_capability', 'manage_options' );
	}

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		// Register settings on admin_init at the top level so options.php (which
		// fires admin_init on save) always sees the whitelisted option group.
		add_action( 'admin_init', array( 'SEMNEWS_Settings', 'register' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_setup' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Action handlers (admin-post.php).
		add_action( 'admin_post_semnews_add_subscriber', array( $this, 'handle_add_subscriber' ) );
		add_action( 'admin_post_semnews_export_csv', array( $this, 'handle_export_csv' ) );
		add_action( 'admin_post_semnews_export_consent_csv', array( $this, 'handle_export_consent_csv' ) );
		add_action( 'admin_post_semnews_subscriber_action', array( $this, 'handle_subscriber_action' ) );
		add_action( 'admin_post_semnews_duplicate_campaign', array( $this, 'handle_duplicate_campaign' ) );
		add_action( 'admin_post_semnews_preview_campaign', array( $this, 'handle_preview_campaign' ) );
		add_action( 'admin_post_semnews_preview_template', array( $this, 'handle_preview_template' ) );
		add_action( 'admin_post_semnews_import_csv', array( $this, 'handle_import_csv' ) );
		add_action( 'admin_post_semnews_save_campaign', array( $this, 'handle_save_campaign' ) );
		add_action( 'admin_post_semnews_build_campaign', array( $this, 'handle_build_campaign' ) );
		add_action( 'admin_post_semnews_send_campaign', array( $this, 'handle_send_campaign' ) );
		add_action( 'admin_post_semnews_unschedule_campaign', array( $this, 'handle_unschedule_campaign' ) );
		add_action( 'admin_post_semnews_pause_campaign', array( $this, 'handle_pause_campaign' ) );
		add_action( 'admin_post_semnews_resume_campaign', array( $this, 'handle_resume_campaign' ) );
		add_action( 'admin_post_semnews_send_test', array( $this, 'handle_send_test' ) );
		add_action( 'admin_post_semnews_delete_campaign', array( $this, 'handle_delete_campaign' ) );
		add_action( 'admin_post_semnews_deliverability_test', array( $this, 'handle_deliverability_test' ) );
		add_action( 'admin_post_semnews_deliverability_recheck', array( $this, 'handle_deliverability_recheck' ) );
		add_action( 'admin_post_semnews_save_wizard', array( $this, 'handle_save_wizard' ) );
		add_action( 'admin_post_semnews_rotate_webhook', array( $this, 'handle_rotate_webhook' ) );
		add_action( 'admin_post_semnews_save_display', array( $this, 'handle_save_display' ) );

		// Pro previews: greyed-out versions of the add-on's controls, rendered
		// through the same hooks the add-on uses so they sit exactly where the
		// real thing appears. Each renderer no-ops once Pro is active.
		add_action( 'semnews_campaign_editor_fields', array( $this, 'render_pro_preview_editor_fields' ), 30, 2 );
		add_action( 'semnews_campaign_send_panel', array( $this, 'render_pro_preview_schedule' ), 30, 2 );
		add_action( 'semnews_display_sections', array( $this, 'render_pro_preview_overlays' ), 30, 2 );
		add_action( 'semnews_dashboard_panels', array( $this, 'render_pro_dashboard_panel' ), 30 );
		add_action( 'semnews_settings_sender_rows', array( $this, 'render_pro_preview_logo_row' ), 30, 2 );
		add_action( 'semnews_settings_privacy_rows', array( $this, 'render_pro_preview_privacy_rows' ), 50, 2 );
	}

	/**
	 * Register the admin menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap = self::capability();

		add_menu_page(
			__( 'Newsletters', 'quintessential-newsletters' ),
			__( 'Newsletters', 'quintessential-newsletters' ),
			$cap,
			'semnews-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-email-alt',
			26
		);

		add_submenu_page( 'semnews-dashboard', __( 'Dashboard', 'quintessential-newsletters' ), __( 'Dashboard', 'quintessential-newsletters' ), $cap, 'semnews-dashboard', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'semnews-dashboard', __( 'Subscribers', 'quintessential-newsletters' ), __( 'Subscribers', 'quintessential-newsletters' ), $cap, 'semnews-subscribers', array( $this, 'render_subscribers' ) );
		add_submenu_page( 'semnews-dashboard', __( 'Newsletters', 'quintessential-newsletters' ), __( 'Newsletters', 'quintessential-newsletters' ), $cap, 'semnews-campaigns', array( $this, 'render_campaigns' ) );
		add_submenu_page( 'semnews-dashboard', __( 'Deliverability', 'quintessential-newsletters' ), __( 'Deliverability', 'quintessential-newsletters' ), $cap, 'semnews-deliverability', array( $this, 'render_deliverability' ) );
		add_submenu_page( 'semnews-dashboard', __( 'Display & Placement', 'quintessential-newsletters' ), __( 'Display', 'quintessential-newsletters' ), $cap, 'semnews-display', array( $this, 'render_display' ) );
		add_submenu_page( 'semnews-dashboard', __( 'Settings', 'quintessential-newsletters' ), __( 'Settings', 'quintessential-newsletters' ), $cap, 'semnews-settings', array( $this, 'render_settings' ) );

		/**
		 * Lets add-ons (e.g. Pro) register their own screens under the
		 * Newsletters menu, before the hidden setup entry.
		 *
		 * @param string $cap Required capability.
		 */
		do_action( 'semnews_admin_menu', $cap );

		// Setup wizard: registered so it is reachable, but hidden from the menu.
		add_submenu_page( 'semnews-dashboard', __( 'Setup', 'quintessential-newsletters' ), __( 'Setup', 'quintessential-newsletters' ), $cap, 'semnews-setup', array( $this, 'render_setup' ) );

		if ( ! semnews_pro_active() ) {
			add_submenu_page( 'semnews-dashboard', __( 'Upgrade to Pro', 'quintessential-newsletters' ), __( 'Upgrade to Pro', 'quintessential-newsletters' ), $cap, 'semnews-upgrade', array( $this, 'render_upgrade' ) );
		}
		remove_submenu_page( 'semnews-dashboard', 'semnews-setup' );
	}

	/**
	 * After activation, send the owner to the setup wizard once.
	 *
	 * @return void
	 */
	public function maybe_redirect_to_setup() {
		if ( ! get_transient( 'semnews_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'semnews_activation_redirect' );

		// Don't hijack bulk/network activations or AJAX.
		if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) || ! current_user_can( self::capability() ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( get_option( 'semnews_setup_done' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=semnews-setup&step=1' ) );
		exit;
	}

	/**
	 * Enqueue admin assets only on our screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'semnews-' ) ) {
			return;
		}

		wp_enqueue_style( 'semnews-admin', SEMNEWS_PLUGIN_URL . 'assets/css/admin.css', array(), SEMNEWS_VERSION );
		wp_style_add_data( 'semnews-admin', 'rtl', 'replace' );
		wp_enqueue_script( 'semnews-admin', SEMNEWS_PLUGIN_URL . 'assets/js/admin.js', array(), SEMNEWS_VERSION, true );
		wp_localize_script(
			'semnews-admin',
			'semnewsEditorL10n',
			array(
				'unsaved' => __( 'You have unsaved changes. This button uses the last saved version of the newsletter and reloads the page, so your edits would be lost. Click Cancel, then "Save draft" to keep them.', 'quintessential-newsletters' ),
				'preview' => __( 'The browser preview shows the last saved version — your unsaved edits will not appear in it yet.', 'quintessential-newsletters' ),
			)
		);

		// The campaign editor benefits from the WYSIWYG editor.
		if ( false !== strpos( $hook, 'semnews-campaigns' ) ) {
			wp_enqueue_editor();
		}
	}

	// ---------------------------------------------------------------------
	// Screen renderers.
	// ---------------------------------------------------------------------

	/**
	 * Verify the current user can manage and die otherwise.
	 *
	 * @return void
	 */
	protected function guard() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'quintessential-newsletters' ) );
		}
	}

	/**
	 * Dashboard screen.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		$this->guard();
		$counts = SEMNEWS_Subscribers::count_all();
		include SEMNEWS_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Subscribers screen.
	 *
	 * @return void
	 */
	public function render_subscribers() {
		$this->guard();

		// Single-subscriber detail view (record + consent history).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display routing only.
		if ( isset( $_GET['action'] ) && 'view' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			$subscriber_id = isset( $_GET['subscriber'] ) ? absint( $_GET['subscriber'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$subscriber    = $subscriber_id ? SEMNEWS_Subscribers::get( $subscriber_id ) : null;
			if ( $subscriber ) {
				$history = SEMNEWS_Consent_Log::get_for_subscriber( (int) $subscriber->id );
				include SEMNEWS_PLUGIN_DIR . 'admin/views/subscriber-view.php';
				return;
			}
		}

		require_once SEMNEWS_PLUGIN_DIR . 'admin/class-semnews-subscribers-list-table.php';
		$this->process_subscriber_bulk();
		include SEMNEWS_PLUGIN_DIR . 'admin/views/subscribers.php';
	}

	/**
	 * Process a WP_List_Table bulk action for subscribers (runs on page load).
	 *
	 * @return void
	 */
	protected function process_subscriber_bulk() {
		$action = '';
		if ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );
		} elseif ( isset( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action2'] ) );
		}

		if ( ! $action || ! in_array( $action, array( 'delete', 'unsubscribe', 'resend', 'bounce' ), true ) ) {
			return;
		}

		check_admin_referer( 'bulk-subscribers' );

		$ids = isset( $_REQUEST['subscriber'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['subscriber'] ) ) : array();
		if ( empty( $ids ) ) {
			$this->redirect( 'semnews-subscribers', 'no_selection' );
		}

		foreach ( $ids as $id ) {
			$subscriber = SEMNEWS_Subscribers::get( $id );
			if ( ! $subscriber ) {
				continue;
			}
			switch ( $action ) {
				case 'delete':
					SEMNEWS_Subscribers::delete( $id );
					break;
				case 'unsubscribe':
					SEMNEWS_Subscribers::unsubscribe( $subscriber );
					break;
				case 'resend':
					if ( SEMNEWS_Subscribers::STATUS_PENDING === $subscriber->status ) {
						SEMNEWS_Mailer::send_confirmation( $subscriber );
					}
					break;
				case 'bounce':
					SEMNEWS_Subscribers::record_bounce( $subscriber->email, 'hard' );
					break;
			}
		}

		$this->redirect( 'semnews-subscribers', 'bulk_done' );
	}

	/**
	 * Campaigns screen (list or editor depending on ?action).
	 *
	 * @return void
	 */
	public function render_campaigns() {
		$this->guard();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view routing; every state change goes through a nonce-verified admin-post handler.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';

		if ( 'edit' === $action || 'new' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view routing; every state change goes through a nonce-verified admin-post handler.
			$campaign_id = isset( $_GET['campaign'] ) ? absint( $_GET['campaign'] ) : 0;
			$campaign    = $campaign_id ? SEMNEWS_Campaigns::get( $campaign_id ) : null;
			include SEMNEWS_PLUGIN_DIR . 'admin/views/campaign-edit.php';
			return;
		}

		include SEMNEWS_PLUGIN_DIR . 'admin/views/campaigns.php';
	}

	/**
	 * Settings screen.
	 *
	 * @return void
	 */
	public function render_settings() {
		$this->guard();
		$settings = semnews_get_settings();
		include SEMNEWS_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Upgrade-to-Pro screen (registered only while the add-on is inactive).
	 *
	 * @return void
	 */
	public function render_upgrade() {
		$this->guard();
		include SEMNEWS_PLUGIN_DIR . 'admin/views/upgrade.php';
	}

	/**
	 * The Upgrade page URL (the target of every Pro badge).
	 *
	 * @return string
	 */
	public static function upgrade_url() {
		return admin_url( 'admin.php?page=semnews-upgrade' );
	}

	/**
	 * A small "Pro" pill linking to the Upgrade page.
	 *
	 * @return string Escaped HTML.
	 */
	protected static function pro_badge() {
		return '<a class="semnews-pro-badge" href="' . esc_url( self::upgrade_url() ) . '">' . esc_html__( 'Pro', 'quintessential-newsletters' ) . '</a>';
	}

	/**
	 * Greyed-out previews of the Pro composer fields (sender identities and
	 * lists & tags segmentation), in the exact spot the add-on renders them.
	 *
	 * @param object|null $campaign  Campaign row or null for new.
	 * @param bool        $is_locked Whether the campaign is read-only.
	 * @return void
	 */
	public function render_pro_preview_editor_fields( $campaign, $is_locked ) {
		if ( semnews_pro_active() || $is_locked ) {
			return;
		}
		?>
		<div class="semnews-pro-note">
			<p>
				<?php echo wp_kses( self::pro_badge(), array( 'a' => array( 'class' => true, 'href' => true ) ) ); ?>
				<?php esc_html_e( 'A separate Pro add-on adds sending from multiple named identities, targeting by lists and tags, and sending only to engaged subscribers.', 'quintessential-newsletters' ); ?>
				<a href="<?php echo esc_url( self::upgrade_url() ); ?>"><?php esc_html_e( 'Learn more →', 'quintessential-newsletters' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Greyed-out preview of the Pro "Schedule for later" form in the Send panel.
	 *
	 * @param object $campaign Campaign row.
	 * @param bool   $can_send Whether sending is currently possible.
	 * @return void
	 */
	public function render_pro_preview_schedule( $campaign, $can_send ) {
		if ( semnews_pro_active() ) {
			return;
		}
		?>
		<div class="semnews-pro-note">
			<p>
				<?php echo wp_kses( self::pro_badge(), array( 'a' => array( 'class' => true, 'href' => true ) ) ); ?>
				<?php esc_html_e( 'Writing now and sending at a chosen date and time is available in the separate Pro add-on.', 'quintessential-newsletters' ); ?>
				<a href="<?php echo esc_url( self::upgrade_url() ); ?>"><?php esc_html_e( 'Learn more →', 'quintessential-newsletters' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Greyed-out preview of the Pro overlay placements on the Display screen.
	 *
	 * @param array  $config Display config.
	 * @param string $opt    Option name prefix.
	 * @return void
	 */
	public function render_pro_preview_overlays( $config, $opt ) {
		if ( semnews_pro_active() ) {
			return;
		}
		?>
		<div class="semnews-pro-note" style="max-width:640px;">
			<p>
				<?php echo wp_kses( self::pro_badge(), array( 'a' => array( 'class' => true, 'href' => true ) ) ); ?>
				<?php esc_html_e( 'Overlay signup placements — popup, slide-in and sticky bar, reusing this same form and double opt-in — are available in the separate Pro add-on.', 'quintessential-newsletters' ); ?>
				<a href="<?php echo esc_url( self::upgrade_url() ); ?>"><?php esc_html_e( 'Learn more →', 'quintessential-newsletters' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Greyed-out preview of the Pro brand-logo row in Settings > Sender, in
	 * the exact spot the add-on renders the real picker.
	 *
	 * @param array  $settings Current settings.
	 * @param string $opt      Option field prefix.
	 * @return void
	 */
	public function render_pro_preview_logo_row( $settings, $opt ) {
		if ( semnews_pro_active() ) {
			return;
		}
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Brand logo', 'quintessential-newsletters' ); ?></th>
			<td>
				<p class="description">
					<?php echo wp_kses( self::pro_badge(), array( 'a' => array( 'class' => true, 'href' => true ) ) ); ?>
					<?php esc_html_e( 'Adding your own logo to every gallery template and to the confirmation and welcome emails is available in the separate Pro add-on.', 'quintessential-newsletters' ); ?>
					<a href="<?php echo esc_url( self::upgrade_url() ); ?>"><?php esc_html_e( 'Learn more →', 'quintessential-newsletters' ); ?></a>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Greyed-out previews of the Pro rows in Settings > Privacy & data
	 * (signup protection extras, the public archive and — when WooCommerce is
	 * active — the checkout opt-in), where the add-on renders the real rows.
	 *
	 * @param array  $settings Current settings.
	 * @param string $opt      Option field prefix.
	 * @return void
	 */
	public function render_pro_preview_privacy_rows( $settings, $opt ) {
		if ( semnews_pro_active() ) {
			return;
		}
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'More list tools', 'quintessential-newsletters' ); ?></th>
			<td>
				<p class="description">
					<?php echo wp_kses( self::pro_badge(), array( 'a' => array( 'class' => true, 'href' => true ) ) ); ?>
					<?php
					if ( class_exists( 'WooCommerce' ) ) {
						esc_html_e( 'A separate Pro add-on adds disposable-email blocking, a reminder to unconfirmed signups, a public newsletter archive, and a WooCommerce checkout opt-in. Free already blocks bots, dead mail domains and confirmation bombing.', 'quintessential-newsletters' );
					} else {
						esc_html_e( 'A separate Pro add-on adds disposable-email blocking, a reminder to unconfirmed signups, and a public newsletter archive. Free already blocks bots, dead mail domains and confirmation bombing.', 'quintessential-newsletters' );
					}
					?>
					<a href="<?php echo esc_url( self::upgrade_url() ); ?>"><?php esc_html_e( 'Learn more →', 'quintessential-newsletters' ); ?></a>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Compact Pro panel on the dashboard (the add-on replaces it with its own).
	 *
	 * @return void
	 */
	public function render_pro_dashboard_panel() {
		if ( semnews_pro_active() ) {
			return;
		}
		?>
		<div class="semnews-panel semnews-panel-pro">
			<h2 class="semnews-panel-pro-head">
				<?php echo wp_kses( self::pro_badge(), array( 'a' => array( 'class' => true, 'href' => true ) ) ); ?>
				<?php esc_html_e( 'Go further with Pro', 'quintessential-newsletters' ); ?>
			</h2>
			<ul class="semnews-pro-list semnews-pro-list-compact">
				<li><?php esc_html_e( 'Welcome series for new subscribers', 'quintessential-newsletters' ); ?></li>
				<li><?php esc_html_e( 'Scheduled sending & automated digests', 'quintessential-newsletters' ); ?></li>
				<li><?php esc_html_e( 'Lists & tags segmentation', 'quintessential-newsletters' ); ?></li>
				<li><?php esc_html_e( 'Popup, slide-in & sticky-bar signup', 'quintessential-newsletters' ); ?></li>
				<li><?php esc_html_e( 'Template gallery, brand logo & previews', 'quintessential-newsletters' ); ?></li>
				<li><?php esc_html_e( 'Engagement insights & engaged-only sends', 'quintessential-newsletters' ); ?></li>
				<li><?php esc_html_e( 'Disposable-email blocking & confirmation reminders', 'quintessential-newsletters' ); ?></li>
				<li><?php esc_html_e( 'Multiple sender identities', 'quintessential-newsletters' ); ?></li>
				<li><?php esc_html_e( 'WooCommerce checkout opt-in', 'quintessential-newsletters' ); ?></li>
				<li><?php esc_html_e( 'Public newsletter archive', 'quintessential-newsletters' ); ?></li>
				<li><?php esc_html_e( 'Priority support & every new feature included', 'quintessential-newsletters' ); ?></li>
			</ul>
			<p><a class="button button-primary" href="<?php echo esc_url( self::upgrade_url() ); ?>"><?php esc_html_e( 'See what’s in Pro', 'quintessential-newsletters' ); ?></a></p>
			<p class="description"><?php esc_html_e( '$5/month, billed annually. Everything in the free plugin stays free and unlimited.', 'quintessential-newsletters' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Deliverability health screen.
	 *
	 * @return void
	 */
	public function render_deliverability() {
		$this->guard();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display toggle.
		$force  = isset( $_GET['recheck'] );
		$report = SEMNEWS_Deliverability::report( $force );
		include SEMNEWS_PLUGIN_DIR . 'admin/views/deliverability.php';
	}

	/**
	 * Display & placement screen.
	 *
	 * @return void
	 */
	public function render_display() {
		$this->guard();
		$config = SEMNEWS_Display::get_config();
		include SEMNEWS_PLUGIN_DIR . 'admin/views/display.php';
	}

	/**
	 * Setup wizard screen.
	 *
	 * @return void
	 */
	public function render_setup() {
		$this->guard();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- step is a display selector.
		$step     = isset( $_GET['step'] ) ? max( 1, min( 3, absint( $_GET['step'] ) ) ) : 1;
		$settings = semnews_get_settings();
		include SEMNEWS_PLUGIN_DIR . 'admin/views/setup.php';
	}

	// ---------------------------------------------------------------------
	// Action handlers.
	// ---------------------------------------------------------------------

	/**
	 * Redirect helper after an action, carrying a notice code. Public/static so
	 * add-on action handlers can share the same notice plumbing.
	 *
	 * @param string $page   Admin page slug.
	 * @param string $notice Notice code.
	 * @param array  $extra  Extra query args.
	 * @return void
	 */
	public static function redirect( $page, $notice = '', $extra = array() ) {
		$args = array( 'page' => $page );
		if ( $notice ) {
			$args['semnews_notice'] = $notice;
		}
		$args = array_merge( $args, $extra );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Add a subscriber manually.
	 *
	 * @return void
	 */
	public function handle_add_subscriber() {
		$this->guard();
		check_admin_referer( 'semnews_add_subscriber' );

		$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : SEMNEWS_Subscribers::STATUS_SUBSCRIBED;
		$basis  = isset( $_POST['basis'] ) ? sanitize_key( wp_unslash( $_POST['basis'] ) ) : SEMNEWS_Subscribers::BASIS_CONSENT;

		if ( ! array_key_exists( $status, SEMNEWS_Subscribers::statuses() ) ) {
			$status = SEMNEWS_Subscribers::STATUS_SUBSCRIBED;
		}

		// Soft opt-in requires the owner to attest the PECR conditions are met.
		if ( SEMNEWS_Subscribers::BASIS_SOFT_OPTIN === $basis && empty( $_POST['soft_optin_attest'] ) ) {
			$this->redirect( 'semnews-subscribers', 'attest_required' );
		}

		$res = SEMNEWS_Subscribers::admin_add( $email, $name, $status, 'admin', $basis );

		$this->redirect( 'semnews-subscribers', $res['success'] ? 'added' : $res['code'] );
	}

	/**
	 * Export subscribers as CSV.
	 *
	 * @return void
	 */
	public function handle_export_csv() {
		$this->guard();
		check_admin_referer( 'semnews_export_csv' );

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=subscribers-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'email', 'name', 'status', 'consent_basis', 'created_at', 'confirmed_at', 'source', 'ip_signup' ) );

		// Stream in chunks: constant memory and no silent truncation, whatever
		// the list size (Pro is unlimited).
		$chunk = 2000;
		$paged = 1;
		do {
			$data = SEMNEWS_Subscribers::query(
				array(
					'per_page' => $chunk,
					'paged'    => $paged,
					'status'   => $status,
					'orderby'  => 'id',
					'order'    => 'ASC',
				)
			);
			foreach ( $data['items'] as $row ) {
				fputcsv(
					$out,
					array_map(
						'semnews_csv_field',
						array(
							$row->email,
							$row->name,
							$row->status,
							isset( $row->consent_basis ) ? $row->consent_basis : '',
							$row->created_at,
							$row->confirmed_at,
							$row->source,
							$row->ip_signup,
						)
					)
				);
			}
			flush();
			$paged++;
		} while ( count( $data['items'] ) === $chunk );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Export the full consent log as CSV — the GDPR Art. 30 "records of
	 * processing" register for newsletter consent. Streamed in chunks.
	 *
	 * @return void
	 */
	public function handle_export_consent_csv() {
		$this->guard();
		check_admin_referer( 'semnews_export_consent_csv' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=consent-register-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'id', 'email', 'event', 'consent_text', 'source', 'ip', 'user_agent', 'created_at_utc' ) );

		$chunk  = 2000;
		$offset = 0;
		do {
			$rows = SEMNEWS_Consent_Log::page( $chunk, $offset );
			foreach ( $rows as $row ) {
				fputcsv(
					$out,
					array_map(
						'semnews_csv_field',
						array(
							$row['id'],
							$row['email'],
							$row['event'],
							$row['consent_text'],
							$row['source'],
							$row['ip'],
							$row['user_agent'],
							$row['created_at'],
						)
					)
				);
			}
			flush();
			$offset += $chunk;
		} while ( count( $rows ) === $chunk );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Single-subscriber actions from the detail screen (resend/unsubscribe/delete).
	 *
	 * @return void
	 */
	public function handle_subscriber_action() {
		$this->guard();

		$id = isset( $_POST['subscriber_id'] ) ? absint( $_POST['subscriber_id'] ) : 0;
		check_admin_referer( 'semnews_subscriber_action_' . $id );

		$do         = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$subscriber = $id ? SEMNEWS_Subscribers::get( $id ) : null;

		if ( ! $subscriber ) {
			$this->redirect( 'semnews-subscribers', 'error' );
		}

		switch ( $do ) {
			case 'resend':
				if ( SEMNEWS_Subscribers::STATUS_PENDING === $subscriber->status ) {
					SEMNEWS_Mailer::send_confirmation( $subscriber );
				}
				$this->redirect( 'semnews-subscribers', 'bulk_done', array( 'action' => 'view', 'subscriber' => $id ) );
				break;
			case 'unsubscribe':
				SEMNEWS_Subscribers::unsubscribe( $subscriber );
				$this->redirect( 'semnews-subscribers', 'bulk_done', array( 'action' => 'view', 'subscriber' => $id ) );
				break;
			case 'delete':
				SEMNEWS_Subscribers::delete( $id );
				$this->redirect( 'semnews-subscribers', 'bulk_done' );
				break;
		}

		$this->redirect( 'semnews-subscribers', 'error' );
	}

	/**
	 * Import subscribers from a pasted list or uploaded CSV (email,name per line).
	 *
	 * @return void
	 */
	public function handle_import_csv() {
		$this->guard();
		check_admin_referer( 'semnews_import_csv' );

		$raw = isset( $_POST['semnews_import'] ) ? wp_unslash( $_POST['semnews_import'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed line by line below.

		// An uploaded CSV file is treated exactly like pasted lines (email,name).
		if ( ! empty( $_FILES['semnews_import_file']['tmp_name'] ) && is_uploaded_file( $_FILES['semnews_import_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$file_raw = file_get_contents( sanitize_text_field( $_FILES['semnews_import_file']['tmp_name'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( false !== $file_raw ) {
				$raw = trim( (string) $file_raw ) . "\n" . (string) $raw;
			}
		}

		$status = isset( $_POST['import_status'] ) ? sanitize_key( wp_unslash( $_POST['import_status'] ) ) : SEMNEWS_Subscribers::STATUS_SUBSCRIBED;
		$basis  = isset( $_POST['basis'] ) ? sanitize_key( wp_unslash( $_POST['basis'] ) ) : SEMNEWS_Subscribers::BASIS_CONSENT;
		if ( ! array_key_exists( $status, SEMNEWS_Subscribers::statuses() ) ) {
			$status = SEMNEWS_Subscribers::STATUS_SUBSCRIBED;
		}

		// Soft opt-in imports require the PECR attestation.
		if ( SEMNEWS_Subscribers::BASIS_SOFT_OPTIN === $basis && empty( $_POST['soft_optin_attest'] ) ) {
			$this->redirect( 'semnews-subscribers', 'attest_required' );
		}

		$lines    = preg_split( '/\r\n|\r|\n/', (string) $raw );
		$imported = 0;
		$skipped  = 0;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = str_getcsv( $line );
			$email = isset( $parts[0] ) ? sanitize_email( trim( $parts[0] ) ) : '';
			$name  = isset( $parts[1] ) ? sanitize_text_field( trim( $parts[1] ) ) : '';

			if ( ! is_email( $email ) ) {
				$skipped++;
				continue;
			}

			$res = SEMNEWS_Subscribers::admin_add( $email, $name, $status, 'import', $basis );
			if ( $res['success'] ) {
				$imported++;
			} else {
				$skipped++;
			}
		}

		$this->redirect( 'semnews-subscribers', 'imported', array( 'imp' => $imported, 'skip' => $skipped ) );
	}

	/**
	 * Save (create or update) a campaign draft.
	 *
	 * @return void
	 */
	public function handle_save_campaign() {
		$this->guard();
		check_admin_referer( 'semnews_save_campaign' );

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;

		// Never edit a campaign that is mid-send or already sent — later batches
		// must deliver the same content as earlier ones.
		if ( $campaign_id ) {
			$existing = SEMNEWS_Campaigns::get( $campaign_id );
			if ( $existing && in_array( $existing->status, array( SEMNEWS_Campaigns::STATUS_SENDING, SEMNEWS_Campaigns::STATUS_SENT ), true ) ) {
				$this->redirect( 'semnews-campaigns', 'campaign_locked', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
			}
		}

		$data = array(
			'subject'   => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
			'preheader' => isset( $_POST['preheader'] ) ? sanitize_text_field( wp_unslash( $_POST['preheader'] ) ) : '',
			'sender_id' => isset( $_POST['sender_id'] ) ? sanitize_key( wp_unslash( $_POST['sender_id'] ) ) : '',
			'body'      => isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in SEMNEWS_Campaigns.
		);

		if ( $campaign_id ) {
			SEMNEWS_Campaigns::update( $campaign_id, $data );
		} else {
			$campaign_id = SEMNEWS_Campaigns::create( $data );
		}

		/**
		 * Fires after a campaign draft was saved, so add-ons can persist their
		 * own composer fields (e.g. the Pro segment picker). Nonce and
		 * capability are already verified; read your fields from $_POST.
		 *
		 * @param int $campaign_id Saved campaign id.
		 */
		do_action( 'semnews_campaign_saved', $campaign_id );

		$this->redirect( 'semnews-campaigns', 'campaign_saved', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
	}

	/**
	 * Generate a campaign body from recent posts using a chosen template, then
	 * save it onto the campaign so the owner can edit before sending.
	 *
	 * @return void
	 */
	public function handle_build_campaign() {
		$this->guard();
		check_admin_referer( 'semnews_build_campaign' );

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;

		if ( $campaign_id ) {
			$existing = SEMNEWS_Campaigns::get( $campaign_id );
			if ( $existing && in_array( $existing->status, array( SEMNEWS_Campaigns::STATUS_SENDING, SEMNEWS_Campaigns::STATUS_SENT ), true ) ) {
				$this->redirect( 'semnews-campaigns', 'campaign_locked', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
			}
		}

		$template    = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : SEMNEWS_Templates::default_template();
		$count       = isset( $_POST['post_count'] ) ? max( 1, min( 50, absint( $_POST['post_count'] ) ) ) : 5;
		$cats        = isset( $_POST['categories'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['categories'] ) ) : array();
		$intro       = isset( $_POST['intro'] ) ? sanitize_textarea_field( wp_unslash( $_POST['intro'] ) ) : '';

		if ( ! SEMNEWS_Templates::exists( $template ) ) {
			$template = SEMNEWS_Templates::default_template();
		}

		// Remember these choices so the "Build from posts" panel keeps them after
		// a reload (e.g. saving the draft), instead of resetting to defaults.
		update_user_meta(
			get_current_user_id(),
			'semnews_build_prefs',
			array(
				'template'   => $template,
				'count'      => $count,
				'categories' => $cats,
			)
		);

		$posts = SEMNEWS_Templates::get_posts(
			array(
				'count'      => $count,
				'categories' => $cats,
				'post_type'  => 'post',
			)
		);

		if ( empty( $posts ) ) {
			$this->redirect( 'semnews-campaigns', 'no_posts', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
		}

		$body = SEMNEWS_Templates::render( $template, $posts, array( 'intro' => $intro ) );

		$data = array(
			'body'     => $body,
			'template' => $template,
		);

		if ( $campaign_id ) {
			SEMNEWS_Campaigns::update( $campaign_id, $data );
		} else {
			$data['subject'] = sprintf(
				/* translators: %s: site name. */
				__( '%s newsletter', 'quintessential-newsletters' ),
				get_bloginfo( 'name' )
			);
			$campaign_id = SEMNEWS_Campaigns::create( $data );
		}

		$this->redirect( 'semnews-campaigns', 'campaign_built', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
	}

	/**
	 * Begin sending a campaign.
	 *
	 * @return void
	 */
	public function handle_send_campaign() {
		$this->guard();
		check_admin_referer( 'semnews_send_campaign' );

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$campaign    = $campaign_id ? SEMNEWS_Campaigns::get( $campaign_id ) : null;

		if ( ! $campaign ) {
			$this->redirect( 'semnews-campaigns', 'campaign_missing' );
		}

		// Only a draft can be sent. This blocks replayed/duplicate submits from
		// rebuilding the queue of a campaign that is already sending or sent
		// (which would re-mail everyone).
		if ( SEMNEWS_Campaigns::STATUS_DRAFT !== $campaign->status ) {
			$this->redirect( 'semnews-campaigns', 'campaign_locked', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
		}

		if ( '' === trim( (string) $campaign->subject ) ) {
			$this->redirect( 'semnews-campaigns', 'no_subject', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
		}

		// CAN-SPAM / honesty: a real postal address must be set before any send.
		if ( '' === trim( (string) semnews_get_option( 'postal_address' ) ) ) {
			$this->redirect( 'semnews-campaigns', 'no_address', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
		}

		// Sender identity must be set.
		if ( '' === trim( (string) semnews_get_option( 'from_email' ) ) ) {
			$this->redirect( 'semnews-campaigns', 'no_sender', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
		}

		$recipients = SEMNEWS_Subscribers::count_by_status( SEMNEWS_Subscribers::STATUS_SUBSCRIBED );
		if ( $recipients < 1 ) {
			$this->redirect( 'semnews-campaigns', 'no_recipients', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
		}

		SEMNEWS_Sender::start_campaign( $campaign_id );

		$this->redirect( 'semnews-campaigns', 'sending', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
	}

	/**
	 * Cancel a schedule (back to draft).
	 *
	 * Scheduling itself is a Pro (add-on) feature, but cancelling stays in core:
	 * if the add-on is deactivated with a newsletter still scheduled, the owner
	 * must always be able to stop it.
	 *
	 * @return void
	 */
	public function handle_unschedule_campaign() {
		$this->guard();
		check_admin_referer( 'semnews_unschedule_campaign' );

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$campaign    = $campaign_id ? SEMNEWS_Campaigns::get( $campaign_id ) : null;

		if ( $campaign && SEMNEWS_Campaigns::STATUS_SCHEDULED === $campaign->status ) {
			SEMNEWS_Campaigns::update(
				$campaign_id,
				array(
					'status'       => SEMNEWS_Campaigns::STATUS_DRAFT,
					'scheduled_at' => null,
				)
			);
		}

		$this->redirect( 'semnews-campaigns', 'campaign_unscheduled', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
	}

	/**
	 * Pause a campaign that is mid-send. In-flight batch rows finish; no new
	 * batches are claimed until resume.
	 *
	 * @return void
	 */
	public function handle_pause_campaign() {
		$this->guard();
		check_admin_referer( 'semnews_pause_campaign' );

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$campaign    = $campaign_id ? SEMNEWS_Campaigns::get( $campaign_id ) : null;

		if ( $campaign && SEMNEWS_Campaigns::STATUS_SENDING === $campaign->status ) {
			SEMNEWS_Campaigns::update( $campaign_id, array( 'status' => SEMNEWS_Campaigns::STATUS_PAUSED ) );
		}

		$this->redirect( 'semnews-campaigns', 'campaign_paused', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
	}

	/**
	 * Resume a paused campaign where it left off (already-sent rows stay sent).
	 *
	 * @return void
	 */
	public function handle_resume_campaign() {
		$this->guard();
		check_admin_referer( 'semnews_resume_campaign' );

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$campaign    = $campaign_id ? SEMNEWS_Campaigns::get( $campaign_id ) : null;

		if ( $campaign && SEMNEWS_Campaigns::STATUS_PAUSED === $campaign->status ) {
			SEMNEWS_Campaigns::update( $campaign_id, array( 'status' => SEMNEWS_Campaigns::STATUS_SENDING ) );
			if ( ! wp_next_scheduled( 'semnews_send_now', array( $campaign_id ) ) ) {
				wp_schedule_single_event( time() + 5, 'semnews_send_now', array( $campaign_id ) );
			}
		}

		$this->redirect( 'semnews-campaigns', 'campaign_resumed', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
	}

	/**
	 * Send a test copy.
	 *
	 * @return void
	 */
	public function handle_send_test() {
		$this->guard();
		check_admin_referer( 'semnews_send_test' );

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$to          = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';
		$campaign    = $campaign_id ? SEMNEWS_Campaigns::get( $campaign_id ) : null;

		if ( $campaign && is_email( $to ) ) {
			SEMNEWS_Mailer::send_test( $campaign, $to );
			$this->redirect( 'semnews-campaigns', 'test_sent', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
		}

		$this->redirect( 'semnews-campaigns', 'test_failed', array( 'action' => 'edit', 'campaign' => $campaign_id ) );
	}

	/**
	 * Duplicate a campaign into a fresh draft.
	 *
	 * @return void
	 */
	public function handle_duplicate_campaign() {
		$this->guard();
		check_admin_referer( 'semnews_duplicate_campaign' );

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$campaign    = $campaign_id ? SEMNEWS_Campaigns::get( $campaign_id ) : null;

		if ( ! $campaign ) {
			$this->redirect( 'semnews-campaigns', 'campaign_missing' );
		}

		$new_id = SEMNEWS_Campaigns::create(
			array(
				/* translators: %s: original subject. */
				'subject'   => sprintf( __( 'Copy of %s', 'quintessential-newsletters' ), $campaign->subject ),
				'preheader' => $campaign->preheader,
				'body'      => $campaign->body,
				'sender_id' => $campaign->sender_id,
				'template'  => $campaign->template,
				'status'    => SEMNEWS_Campaigns::STATUS_DRAFT,
			)
		);

		$this->redirect( 'semnews-campaigns', 'campaign_duplicated', array( 'action' => 'edit', 'campaign' => $new_id ) );
	}

	/**
	 * Render a full browser preview of a campaign (exactly the wrapped HTML that
	 * would be emailed, with merge tags resolved against a sample subscriber).
	 *
	 * @return void
	 */
	public function handle_preview_campaign() {
		$this->guard();
		check_admin_referer( 'semnews_preview_campaign' );

		$campaign_id = isset( $_GET['campaign'] ) ? absint( $_GET['campaign'] ) : 0;
		$campaign    = $campaign_id ? SEMNEWS_Campaigns::get( $campaign_id ) : null;

		if ( ! $campaign ) {
			wp_die( esc_html__( 'Newsletter not found.', 'quintessential-newsletters' ) );
		}

		$sample         = new stdClass();
		$sample->id     = 0;
		$sample->email  = 'subscriber@example.com';
		$sample->name   = __( 'Sample Subscriber', 'quintessential-newsletters' );
		$sample->token  = 'preview';
		$sample->status = SEMNEWS_Subscribers::STATUS_SUBSCRIBED;

		$body = SEMNEWS_Mailer::personalize( (string) $campaign->body, $sample );
		$html = SEMNEWS_Mailer::wrap( $campaign->subject, $body, $sample, $campaign->preheader );

		header( 'Content-Type: text/html; charset=utf-8' );
		echo "<!DOCTYPE html>\n";
		echo wp_kses( $html, semnews_allowed_email_document_html() );
		exit;
	}

	/**
	 * Delete a campaign.
	 *
	 * @return void
	 */
	public function handle_delete_campaign() {
		$this->guard();
		check_admin_referer( 'semnews_delete_campaign' );

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		if ( $campaign_id ) {
			SEMNEWS_Campaigns::delete( $campaign_id );
		}

		$this->redirect( 'semnews-campaigns', 'campaign_deleted' );
	}

	/**
	 * Render a sample of a registered template in the browser, built from the
	 * site's latest real posts, wrapped in the shared email layout — so what
	 * you preview is what a subscriber would get.
	 *
	 * @return void
	 */
	public function handle_preview_template() {
		$this->guard();
		check_admin_referer( 'semnews_preview_template' );

		$template = isset( $_GET['template'] ) ? sanitize_key( $_GET['template'] ) : '';
		if ( ! SEMNEWS_Templates::exists( $template ) ) {
			$template = SEMNEWS_Templates::default_template();
		}

		$posts = SEMNEWS_Templates::get_posts( array( 'count' => 3 ) );
		if ( empty( $posts ) ) {
			wp_die( esc_html__( 'Publish a post first — template previews are built from your latest real posts.', 'quintessential-newsletters' ) );
		}

		$labels = SEMNEWS_Templates::get_templates();
		$label  = isset( $labels[ $template ]['label'] ) ? $labels[ $template ]['label'] : $template;

		$html = SEMNEWS_Templates::render(
			$template,
			$posts,
			array(
				'intro'       => __( 'A short intro line looks like this.', 'quintessential-newsletters' ),
				'custom_html' => SEMNEWS_Templates::custom_html_starter(),
			)
		);
		$html = SEMNEWS_Campaigns::sanitize_body( $html );

		/* translators: %s: template name. */
		$subject = sprintf( __( 'Template preview: %s', 'quintessential-newsletters' ), $label );

		header( 'Content-Type: text/html; charset=utf-8' );
		echo "<!DOCTYPE html>\n";
		echo wp_kses( SEMNEWS_Mailer::wrap( $subject, $html ), semnews_allowed_email_document_html() );
		exit;
	}

	/**
	 * Send a deliverability self-test email.
	 *
	 * @return void
	 */
	public function handle_deliverability_test() {
		$this->guard();
		check_admin_referer( 'semnews_deliverability_test' );

		$to = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';
		if ( ! is_email( $to ) ) {
			$to = wp_get_current_user()->user_email;
		}
		$ok = SEMNEWS_Deliverability::send_test( $to );

		$this->redirect( 'semnews-deliverability', $ok ? 'deliv_test_sent' : 'deliv_test_failed' );
	}

	/**
	 * Re-run the DNS checks (clears the cached report).
	 *
	 * @return void
	 */
	public function handle_deliverability_recheck() {
		$this->guard();
		check_admin_referer( 'semnews_deliverability_recheck' );

		SEMNEWS_Deliverability::clear_cache();
		$this->redirect( 'semnews-deliverability', '', array( 'recheck' => 1 ) );
	}

	/**
	 * Rotate the inbound webhook secret.
	 *
	 * @return void
	 */
	public function handle_rotate_webhook() {
		$this->guard();
		check_admin_referer( 'semnews_rotate_webhook' );

		SEMNEWS_Webhook::rotate_secret();
		$this->redirect( 'semnews-settings', 'webhook_rotated' );
	}

	/**
	 * Save the display / placement configuration.
	 *
	 * @return void
	 */
	public function handle_save_display() {
		$this->guard();
		check_admin_referer( 'semnews_save_display' );

		$raw = isset( $_POST['semnews_display'] ) ? wp_unslash( $_POST['semnews_display'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field-by-field in SEMNEWS_Display::save_config().
		SEMNEWS_Display::save_config( is_array( $raw ) ? $raw : array() );

		$this->redirect( 'semnews-display', 'display_saved' );
	}

	/**
	 * Save one step of the setup wizard.
	 *
	 * @return void
	 */
	public function handle_save_wizard() {
		$this->guard();
		check_admin_referer( 'semnews_save_wizard' );

		$step     = isset( $_POST['step'] ) ? max( 1, min( 3, absint( $_POST['step'] ) ) ) : 1;
		$settings = semnews_get_settings();

		if ( 1 === $step ) {
			if ( isset( $_POST['from_name'] ) ) {
				$settings['from_name'] = sanitize_text_field( wp_unslash( $_POST['from_name'] ) );
			}
			$from = isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '';
			if ( is_email( $from ) ) {
				$settings['from_email'] = $from;
			}
			$reply = isset( $_POST['reply_to'] ) ? sanitize_email( wp_unslash( $_POST['reply_to'] ) ) : '';
			$settings['reply_to'] = is_email( $reply ) ? $reply : $settings['from_email'];
		} elseif ( 2 === $step ) {
			if ( isset( $_POST['company_name'] ) ) {
				$settings['company_name'] = sanitize_text_field( wp_unslash( $_POST['company_name'] ) );
			}
			if ( isset( $_POST['postal_address'] ) ) {
				$settings['postal_address'] = sanitize_textarea_field( wp_unslash( $_POST['postal_address'] ) );
			}
		} elseif ( 3 === $step ) {
			$settings['double_optin'] = empty( $_POST['double_optin'] ) ? 0 : 1;
			if ( isset( $_POST['consent_text'] ) ) {
				$settings['consent_text'] = sanitize_text_field( wp_unslash( $_POST['consent_text'] ) );
			}
		}

		update_option( 'semnews_settings', $settings );

		if ( 3 === $step || isset( $_POST['finish'] ) ) {
			update_option( 'semnews_setup_done', 1 );
			$this->redirect( 'semnews-dashboard', 'setup_done' );
		}

		$this->redirect( 'semnews-setup', '', array( 'step' => $step + 1 ) );
	}

	/**
	 * Render the result notice (if any) at the top of a screen.
	 *
	 * @return void
	 */
	public static function render_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, no state change.
		$code = isset( $_GET['semnews_notice'] ) ? sanitize_key( wp_unslash( $_GET['semnews_notice'] ) ) : '';
		if ( ! $code ) {
			return;
		}
		list( $type, $message ) = self::notice_for( $code );
		if ( ! $message ) {
			return;
		}
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			'error' === $type ? 'error' : 'success',
			esc_html( $message )
		);
	}

	/**
	 * Map a notice code to a human message for display.
	 *
	 * @param string $code Notice code.
	 * @return array { type, message }
	 */
	public static function notice_for( $code ) {
		$map = array(
			'added'            => array( 'success', __( 'Subscriber added.', 'quintessential-newsletters' ) ),
			'invalid_email'    => array( 'error', __( 'That email address is not valid.', 'quintessential-newsletters' ) ),
			'suppressed'       => array( 'error', __( 'That address previously unsubscribed or was erased and cannot be re-added. They can re-subscribe themselves via the form.', 'quintessential-newsletters' ) ),
			'error'            => array( 'error', __( 'Something went wrong.', 'quintessential-newsletters' ) ),
			'bulk_done'        => array( 'success', __( 'Action applied.', 'quintessential-newsletters' ) ),
			'no_selection'     => array( 'error', __( 'No subscribers were selected.', 'quintessential-newsletters' ) ),
			'imported'         => array( 'success', __( 'Import complete.', 'quintessential-newsletters' ) ),
			'campaign_saved'   => array( 'success', __( 'Newsletter saved.', 'quintessential-newsletters' ) ),
			'campaign_locked'  => array( 'error', __( 'This newsletter is sending or already sent and can no longer be edited or re-sent.', 'quintessential-newsletters' ) ),
			'campaign_duplicated' => array( 'success', __( 'Newsletter duplicated — you are editing the copy.', 'quintessential-newsletters' ) ),
			'campaign_unscheduled' => array( 'success', __( 'Schedule cancelled — the newsletter is a draft again.', 'quintessential-newsletters' ) ),
			'campaign_paused'      => array( 'success', __( 'Sending paused. Resume any time — it picks up where it left off.', 'quintessential-newsletters' ) ),
			'campaign_resumed'     => array( 'success', __( 'Sending resumed.', 'quintessential-newsletters' ) ),
			'campaign_deleted' => array( 'success', __( 'Newsletter deleted.', 'quintessential-newsletters' ) ),
			'sending'          => array( 'success', __( 'Sending has started. Delivery happens in the background.', 'quintessential-newsletters' ) ),
			'no_subject'       => array( 'error', __( 'Please add a subject before sending.', 'quintessential-newsletters' ) ),
			'no_address'       => array( 'error', __( 'Add your physical postal address in Settings before sending — it is legally required in every newsletter.', 'quintessential-newsletters' ) ),
			'no_sender'        => array( 'error', __( 'Set a From email address in Settings before sending.', 'quintessential-newsletters' ) ),
			'no_recipients'    => array( 'error', __( 'There are no confirmed subscribers to send to yet.', 'quintessential-newsletters' ) ),
			'test_sent'        => array( 'success', __( 'Test email sent.', 'quintessential-newsletters' ) ),
			'test_failed'      => array( 'error', __( 'Could not send the test email. Check the address.', 'quintessential-newsletters' ) ),
			'campaign_built'   => array( 'success', __( 'Newsletter built from your latest posts. Review and edit it below.', 'quintessential-newsletters' ) ),
			'no_posts'         => array( 'error', __( 'No matching posts were found for those settings.', 'quintessential-newsletters' ) ),
			'deliv_test_sent'  => array( 'success', __( 'Deliverability test email sent. Check your inbox and inspect the headers.', 'quintessential-newsletters' ) ),
			'deliv_test_failed' => array( 'error', __( 'Could not send the deliverability test. Check your From address.', 'quintessential-newsletters' ) ),
			'webhook_rotated'  => array( 'success', __( 'Webhook secret rotated. Update it in your mail provider.', 'quintessential-newsletters' ) ),
			'setup_done'       => array( 'success', __( 'Setup complete — you are ready to send honestly. 🎉', 'quintessential-newsletters' ) ),
			'display_saved'    => array( 'success', __( 'Display & placement settings saved.', 'quintessential-newsletters' ) ),
			'attest_required'  => array( 'error', __( 'To add people under soft opt-in you must confirm the PECR conditions are met.', 'quintessential-newsletters' ) ),
		);

		/**
		 * Lets add-ons register their own notice codes ({ code => [type, message] }).
		 *
		 * @param array $map Notice map.
		 */
		$map = apply_filters( 'semnews_admin_notices', $map );

		return isset( $map[ $code ] ) ? $map[ $code ] : array( '', '' );
	}
}
