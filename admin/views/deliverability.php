<?php
/**
 * Deliverability health screen.
 *
 * @package SimpleEmailNewsletters
 * @var array $report Result of SEMNEWS_Deliverability::report().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$semnews_status   = SEMNEWS_Deliverability::overall_status( $report );
$semnews_status_l = array(
	'good' => __( 'Looking healthy', 'quintessential-newsletters' ),
	'warn' => __( 'Some gaps to close', 'quintessential-newsletters' ),
	'bad'  => __( 'Action needed', 'quintessential-newsletters' ),
);

/**
 * Render a single check row.
 *
 * @param bool   $ok    Pass/fail.
 * @param string $label Label.
 * @param string $detail Detail HTML (already escaped).
 */
$semnews_row = function ( $ok, $label, $detail ) {
	printf(
		'<li class="%1$s"><span class="semnews-check-icon" aria-hidden="true">%2$s</span><span class="semnews-check-body"><strong>%3$s</strong><span class="description">%4$s</span></span></li>',
		$ok ? 'is-ok' : 'is-warn',
		$ok ? '✓' : '!',
		esc_html( $label ),
		wp_kses_post( $detail )
	);
};
?>
<div class="wrap semnews-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Deliverability', 'quintessential-newsletters' ); ?></h1>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
		<input type="hidden" name="action" value="semnews_deliverability_recheck" />
		<?php wp_nonce_field( 'semnews_deliverability_recheck' ); ?>
		<button type="submit" class="page-title-action"><?php esc_html_e( 'Re-check now', 'quintessential-newsletters' ); ?></button>
	</form>
	<hr class="wp-header-end" />

	<?php SEMNEWS_Admin::render_notice(); ?>

	<p class="description" style="max-width:46em;">
		<?php esc_html_e( 'These checks look at the live DNS for your From-address domain. Getting SPF, DKIM and DMARC in place is the single biggest thing you can do to land in the inbox and prove your mail is really from you.', 'quintessential-newsletters' ); ?>
	</p>

	<?php if ( empty( $report['dns_available'] ) ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'DNS lookups are disabled on this server, so live checks could not run. The copy-paste records below are still correct to add at your DNS host.', 'quintessential-newsletters' ); ?></p></div>
	<?php endif; ?>

	<div class="semnews-columns semnews-editor">
		<div class="semnews-editor-main">
			<div class="semnews-panel semnews-deliv-status semnews-deliv-<?php echo esc_attr( $semnews_status ); ?>">
				<h2><?php echo esc_html( isset( $semnews_status_l[ $semnews_status ] ) ? $semnews_status_l[ $semnews_status ] : '' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: 1: sending domain, 2: site domain. */
						esc_html__( 'Sending domain: %1$s', 'quintessential-newsletters' ),
						'<code>' . esc_html( $report['domain'] ? $report['domain'] : __( '(no From address set)', 'quintessential-newsletters' ) ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);
					?>
				</p>
			</div>

			<div class="semnews-panel">
				<h2><?php esc_html_e( 'Checks', 'quintessential-newsletters' ); ?></h2>
				<ul class="semnews-checklist">
					<?php
					$semnews_row(
						! empty( $report['aligned'] ),
						__( 'From address is on your site’s domain', 'quintessential-newsletters' ),
						! empty( $report['aligned'] )
							? esc_html__( 'Aligned — best for trust and DMARC.', 'quintessential-newsletters' )
							: sprintf(
								/* translators: %s: site domain. */
								esc_html__( 'Your From domain differs from your site (%s). Sending from your own domain improves trust and alignment.', 'quintessential-newsletters' ),
								esc_html( $report['site_domain'] )
							)
					);

					$semnews_row(
						! empty( $report['spf']['found'] ),
						'SPF',
						! empty( $report['spf']['found'] )
							? '<code>' . esc_html( $report['spf']['record'] ) . '</code>'
							: esc_html__( 'No SPF record found. Add one so receivers know which servers may send for you.', 'quintessential-newsletters' )
					);

					$semnews_row(
						! empty( $report['dkim']['found'] ),
						'DKIM',
						! empty( $report['dkim']['found'] )
							? sprintf(
								/* translators: %s: comma-separated selectors. */
								esc_html__( 'Detected selector(s): %s', 'quintessential-newsletters' ),
								'<code>' . esc_html( implode( ', ', $report['dkim']['selectors'] ) ) . '</code>'
							)
							: esc_html__( 'No common DKIM selector detected. Your mail host or SMTP plugin sets this up — enable DKIM signing there, then re-check.', 'quintessential-newsletters' )
					);

					$semnews_row(
						! empty( $report['dmarc']['found'] ),
						'DMARC',
						! empty( $report['dmarc']['found'] )
							? '<code>' . esc_html( $report['dmarc']['record'] ) . '</code>'
							: esc_html__( 'No DMARC record found. Start with a monitoring policy (below) — it is safe and tells you who sends as you.', 'quintessential-newsletters' )
					);
					?>
				</ul>
			</div>

			<?php if ( empty( $report['spf']['found'] ) || empty( $report['dmarc']['found'] ) ) : ?>
				<div class="semnews-panel">
					<h2><?php esc_html_e( 'Copy-paste DNS records', 'quintessential-newsletters' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Add these TXT records at your DNS host, then click Re-check. Review them for your own sending services first.', 'quintessential-newsletters' ); ?></p>

					<?php if ( empty( $report['spf']['found'] ) ) : ?>
						<p><strong>SPF</strong> — <?php esc_html_e( 'TXT record on', 'quintessential-newsletters' ); ?> <code><?php echo esc_html( $report['domain'] ); ?></code></p>
						<textarea class="large-text code" rows="2" readonly onclick="this.select()"><?php echo esc_textarea( SEMNEWS_Deliverability::suggested_spf() ); ?></textarea>
					<?php endif; ?>

					<?php if ( empty( $report['dmarc']['found'] ) ) : ?>
						<p style="margin-top:16px;"><strong>DMARC</strong> — <?php esc_html_e( 'TXT record on', 'quintessential-newsletters' ); ?> <code><?php echo esc_html( SEMNEWS_Deliverability::dmarc_host() ); ?></code></p>
						<textarea class="large-text code" rows="2" readonly onclick="this.select()"><?php echo esc_textarea( SEMNEWS_Deliverability::suggested_dmarc() ); ?></textarea>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="semnews-editor-side">
			<div class="semnews-panel">
				<h2><?php esc_html_e( 'Send myself a test', 'quintessential-newsletters' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="semnews_deliverability_test" />
					<?php wp_nonce_field( 'semnews_deliverability_test' ); ?>
					<p><input type="email" name="test_email" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" /></p>
					<?php submit_button( __( 'Send deliverability test', 'quintessential-newsletters' ), 'secondary', 'submit', false ); ?>
					<p class="description"><?php esc_html_e( 'Open the message in your mailbox and confirm SPF, DKIM and DMARC all show “pass” in the headers.', 'quintessential-newsletters' ); ?></p>
				</form>
			</div>

			<div class="semnews-panel">
				<h2><?php esc_html_e( 'Tips', 'quintessential-newsletters' ); ?></h2>
				<ul class="semnews-quickstart">
					<li><?php esc_html_e( 'Send from an address on your own domain.', 'quintessential-newsletters' ); ?></li>
					<li><?php esc_html_e( 'Use a dedicated SMTP service for reliable delivery.', 'quintessential-newsletters' ); ?></li>
					<li><?php esc_html_e( 'Keep a real reply-to inbox a person reads.', 'quintessential-newsletters' ); ?></li>
					<li><?php esc_html_e( 'Warm up new domains by sending gradually.', 'quintessential-newsletters' ); ?></li>
				</ul>
			</div>
		</div>
	</div>
</div>
