<?php
/**
 * Dashboard screen.
 *
 * @package QuintessentialNewsletters
 * @var array $counts Subscriber counts by status (passed from SEMNEWS_Admin).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$semnews_confirmed = isset( $counts['subscribed'] ) ? (int) $counts['subscribed'] : 0;

// Compliance checklist (the "trust mirror").
//
// Deliberately a "strictest common denominator" across the major regimes
// (GDPR/PECR, CAN-SPAM, CASL, Spam Act): these laws follow where SUBSCRIBERS
// live, not where the sender is, so meeting all of them at once is the only
// safe default. The disclaimer below the list says so explicitly.
$semnews_checks = array(
	array(
		'ok'    => (bool) semnews_get_option( 'double_optin', 1 ),
		'label' => __( 'Double opt-in is enabled', 'quintessential-newsletters' ),
		'hint'  => __( 'Subscribers confirm by email first — the reliable way to prove consent under GDPR Art. 7 (EU/UK), and effectively required in Germany.', 'quintessential-newsletters' ),
	),
	array(
		'ok'    => '' !== trim( (string) semnews_get_option( 'postal_address' ) ),
		'label' => __( 'A physical postal address is set', 'quintessential-newsletters' ),
		'hint'  => __( 'Required in every email by CAN-SPAM (US) and CASL (Canada); expected under EU/UK transparency rules.', 'quintessential-newsletters' ),
	),
	array(
		'ok'    => (int) semnews_get_option( 'privacy_policy_page' ) > 0,
		'label' => __( 'A privacy policy page is linked', 'quintessential-newsletters' ),
		'hint'  => __( 'Informed consent: GDPR Art. 13 (EU/UK); also expected under CCPA (California) and PIPEDA (Canada).', 'quintessential-newsletters' ),
	),
	array(
		'ok'    => true,
		'label' => __( 'No open/click tracking', 'quintessential-newsletters' ),
		'hint'  => __( 'This plugin contains no tracking pixels or click redirects at all — which also sidesteps EU ePrivacy consent requirements for pixels.', 'quintessential-newsletters' ),
	),
	array(
		'ok'    => (int) semnews_get_option( 'retention_days', 30 ) > 0,
		'label' => __( 'Stale unconfirmed signups are auto-deleted', 'quintessential-newsletters' ),
		'hint'  => __( 'GDPR data minimisation (Art. 5) — and safe practice everywhere else.', 'quintessential-newsletters' ),
	),
	array(
		'ok'    => true,
		'label' => __( 'Consent records are kept', 'quintessential-newsletters' ),
		'hint'  => __( 'Every signup\'s exact wording, time, IP and source are logged — the proof CASL (Canada) and GDPR Art. 7 (EU/UK) demand. Export it any time from Subscribers.', 'quintessential-newsletters' ),
	),
	array(
		'ok'    => true,
		'label' => __( 'Unsubscribe links never expire', 'quintessential-newsletters' ),
		'hint'  => __( 'One-click (RFC 8058) and permanent — exceeding Australia\'s Spam Act rule that unsubscribe must work for at least 30 days.', 'quintessential-newsletters' ),
	),
);
?>
<?php
// Sending health: everything (sends, cleanup) rides on WP-Cron, so
// surface it clearly when cron looks stalled instead of letting a campaign sit
// at "Sending… 0" with no explanation.
$semnews_next_tick  = wp_next_scheduled( SEMNEWS_Install::CRON_PROCESS_QUEUE );
$semnews_cron_late  = ( false === $semnews_next_tick ) || ( $semnews_next_tick < time() - 15 * MINUTE_IN_SECONDS );
$semnews_mail_error = get_transient( 'semnews_last_mail_error' );
?>
<div class="wrap semnews-wrap">
	<h1><?php esc_html_e( 'Newsletters', 'quintessential-newsletters' ); ?></h1>
	<?php SEMNEWS_Admin::render_notice(); ?>

	<?php if ( $semnews_cron_late ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'WP-Cron looks stalled.', 'quintessential-newsletters' ); ?></strong>
				<?php esc_html_e( 'Sending and cleanup both run on WP-Cron, and the send queue tick is overdue. On low-traffic sites, or if DISABLE_WP_CRON is set, configure a real server cron that hits wp-cron.php every 5 minutes.', 'quintessential-newsletters' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $semnews_mail_error ) : ?>
		<div class="notice notice-error inline">
			<p>
				<strong><?php esc_html_e( 'The last email send reported an error:', 'quintessential-newsletters' ); ?></strong>
				<code><?php echo esc_html( $semnews_mail_error ); ?></code>
				<?php esc_html_e( 'This usually means an SMTP/mail configuration problem.', 'quintessential-newsletters' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php
	$semnews_stats = array(
		array( 'n' => $semnews_confirmed, 'label' => __( 'Confirmed subscribers', 'quintessential-newsletters' ), 'status' => 'subscribed' ),
		array( 'n' => isset( $counts['pending'] ) ? $counts['pending'] : 0, 'label' => __( 'Pending confirmation', 'quintessential-newsletters' ), 'status' => 'pending' ),
		array( 'n' => isset( $counts['unsubscribed'] ) ? $counts['unsubscribed'] : 0, 'label' => __( 'Unsubscribed', 'quintessential-newsletters' ), 'status' => 'unsubscribed' ),
	);
	?>
	<div class="semnews-cards">
		<?php foreach ( $semnews_stats as $semnews_stat ) : ?>
			<div class="semnews-card semnews-card-stat">
				<span class="semnews-stat-number"><?php echo esc_html( number_format_i18n( $semnews_stat['n'] ) ); ?></span>
				<span class="semnews-stat-label"><?php echo esc_html( $semnews_stat['label'] ); ?></span>
				<a class="semnews-stat-link" href="<?php echo esc_url( admin_url( 'admin.php?page=semnews-subscribers&status=' . $semnews_stat['status'] ) ); ?>"><?php esc_html_e( 'View →', 'quintessential-newsletters' ); ?></a>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="semnews-columns">
		<div class="semnews-panel">
			<h2><?php esc_html_e( 'Quick start', 'quintessential-newsletters' ); ?></h2>
			<ol class="semnews-quickstart">
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=semnews-settings' ) ); ?>"><?php esc_html_e( 'Set your sender name, From address and postal address', 'quintessential-newsletters' ); ?></a></li>
				<li><?php esc_html_e( 'Add the signup form to a page using the', 'quintessential-newsletters' ); ?> <code>[semnews_newsletter]</code> <?php esc_html_e( 'shortcode, the Newsletter Signup block, or the widget', 'quintessential-newsletters' ); ?></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=semnews-campaigns&action=new' ) ); ?>"><?php esc_html_e( 'Write and send your first newsletter', 'quintessential-newsletters' ); ?></a></li>
			</ol>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=semnews-campaigns&action=new' ) ); ?>"><?php esc_html_e( 'New newsletter', 'quintessential-newsletters' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=semnews-deliverability' ) ); ?>"><?php esc_html_e( 'Check deliverability', 'quintessential-newsletters' ); ?></a>
			</p>
		</div>

		<div class="semnews-panel">
			<h2><?php esc_html_e( 'Compliance status', 'quintessential-newsletters' ); ?></h2>
			<p class="description"><?php esc_html_e( 'An honest newsletter at a glance. Aim for all green.', 'quintessential-newsletters' ); ?></p>
			<ul class="semnews-checklist">
				<?php foreach ( $semnews_checks as $semnews_check ) : ?>
					<li class="<?php echo $semnews_check['ok'] ? 'is-ok' : 'is-warn'; ?>">
						<span class="semnews-check-icon" aria-hidden="true"><?php echo $semnews_check['ok'] ? '✓' : '!'; ?></span>
						<span class="semnews-check-body">
							<strong><?php echo esc_html( $semnews_check['label'] ); ?></strong>
							<span class="description"><?php echo esc_html( $semnews_check['hint'] ); ?></span>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="description semnews-compliance-note">
				<?php esc_html_e( 'Based on the strictest common requirements across GDPR/PECR (EU & UK), CAN-SPAM (US), CASL (Canada) and the Spam Act (Australia). These laws follow where your subscribers live, not where you are — so meeting all of them at once is the safe default. This is guidance, not legal advice.', 'quintessential-newsletters' ); ?>
			</p>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=semnews-settings' ) ); ?>"><?php esc_html_e( 'Review settings', 'quintessential-newsletters' ); ?></a></p>
		</div>

		<?php
		/**
		 * Lets add-ons render their own dashboard panels (e.g. Pro license
		 * status) as columns alongside Quick start and Compliance status.
		 * The free plugin renders its compact "Go further with Pro" column
		 * here while the add-on is not active.
		 */
		do_action( 'semnews_dashboard_panels' );
		?>
	</div>
</div>
