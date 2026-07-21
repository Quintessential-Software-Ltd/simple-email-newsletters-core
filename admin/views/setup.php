<?php
/**
 * Setup wizard (3 steps).
 *
 * @package QuintessentialNewsletters
 * @var int   $step     Current step (1-3).
 * @var array $settings Current settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$semnews_steps = array(
	1 => __( 'Sender', 'quintessential-newsletters' ),
	2 => __( 'Legal footer', 'quintessential-newsletters' ),
	3 => __( 'Consent', 'quintessential-newsletters' ),
);
$semnews_post_url   = admin_url( 'admin-post.php' );
$semnews_skip_url   = admin_url( 'admin.php?page=semnews-dashboard' );
$semnews_site_host  = SEMNEWS_Deliverability::site_domain();
?>
<div class="wrap semnews-wrap semnews-setup">
	<h1><?php esc_html_e( 'Welcome to Quintessential Newsletters', 'quintessential-newsletters' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Three quick steps and you are ready to send honest, GDPR-friendly newsletters.', 'quintessential-newsletters' ); ?></p>

	<ol class="semnews-wizard-steps">
		<?php foreach ( $semnews_steps as $semnews_step_no => $semnews_label ) : ?>
			<li class="<?php echo $semnews_step_no === $step ? 'is-current' : ( $semnews_step_no < $step ? 'is-done' : '' ); ?>">
				<span class="semnews-step-num"><?php echo (int) $semnews_step_no; ?></span> <?php echo esc_html( $semnews_label ); ?>
			</li>
		<?php endforeach; ?>
	</ol>

	<div class="semnews-panel" style="max-width:640px;">
		<form method="post" action="<?php echo esc_url( $semnews_post_url ); ?>">
			<input type="hidden" name="action" value="semnews_save_wizard" />
			<input type="hidden" name="step" value="<?php echo (int) $step; ?>" />
			<?php wp_nonce_field( 'semnews_save_wizard' ); ?>

			<?php if ( 1 === $step ) : ?>
				<h2><?php esc_html_e( 'Who is the newsletter from?', 'quintessential-newsletters' ); ?></h2>
				<p>
					<label for="w-from-name"><strong><?php esc_html_e( 'From name', 'quintessential-newsletters' ); ?></strong></label><br />
					<input type="text" id="w-from-name" name="from_name" class="regular-text" value="<?php echo esc_attr( $settings['from_name'] ); ?>" />
				</p>
				<p>
					<label for="w-from-email"><strong><?php esc_html_e( 'From email', 'quintessential-newsletters' ); ?></strong></label><br />
					<input type="email" id="w-from-email" name="from_email" class="regular-text" value="<?php echo esc_attr( $settings['from_email'] ); ?>" />
					<br /><span class="description">
						<?php
						/* translators: %s: site domain. */
						printf( esc_html__( 'For best delivery, use an address on your own domain (e.g. newsletter@%s).', 'quintessential-newsletters' ), esc_html( $semnews_site_host ) );
						?>
					</span>
				</p>
				<p>
					<label for="w-reply"><strong><?php esc_html_e( 'Reply-to email', 'quintessential-newsletters' ); ?></strong></label><br />
					<input type="email" id="w-reply" name="reply_to" class="regular-text" value="<?php echo esc_attr( $settings['reply_to'] ); ?>" />
					<br /><span class="description"><?php esc_html_e( 'A real inbox a person reads.', 'quintessential-newsletters' ); ?></span>
				</p>

			<?php elseif ( 2 === $step ) : ?>
				<h2><?php esc_html_e( 'Your legal footer', 'quintessential-newsletters' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Anti-spam law requires a real postal address in every newsletter. It is shown in the footer and builds trust.', 'quintessential-newsletters' ); ?></p>
				<p>
					<label for="w-company"><strong><?php esc_html_e( 'Company / sender name', 'quintessential-newsletters' ); ?></strong></label><br />
					<input type="text" id="w-company" name="company_name" class="regular-text" value="<?php echo esc_attr( $settings['company_name'] ); ?>" />
				</p>
				<p>
					<label for="w-address"><strong><?php esc_html_e( 'Physical postal address', 'quintessential-newsletters' ); ?></strong></label><br />
					<textarea id="w-address" name="postal_address" rows="3" class="large-text"><?php echo esc_textarea( $settings['postal_address'] ); ?></textarea>
				</p>

			<?php else : ?>
				<h2><?php esc_html_e( 'How people consent', 'quintessential-newsletters' ); ?></h2>
				<p>
					<label>
						<input type="checkbox" name="double_optin" value="1" <?php checked( $settings['double_optin'] ); ?> />
						<strong><?php esc_html_e( 'Use double opt-in (recommended)', 'quintessential-newsletters' ); ?></strong>
					</label>
					<br /><span class="description"><?php esc_html_e( 'People confirm by email before they are added — the honest, GDPR-friendly default.', 'quintessential-newsletters' ); ?></span>
				</p>
				<p>
					<label for="w-consent"><strong><?php esc_html_e( 'Consent text', 'quintessential-newsletters' ); ?></strong></label><br />
					<input type="text" id="w-consent" name="consent_text" class="large-text" value="<?php echo esc_attr( $settings['consent_text'] ); ?>" />
					<br /><span class="description"><?php esc_html_e( 'Shown next to the (never pre-ticked) consent checkbox and recorded with every signup.', 'quintessential-newsletters' ); ?></span>
				</p>
			<?php endif; ?>

			<p style="margin-top:24px;">
				<?php submit_button( 3 === $step ? __( 'Finish', 'quintessential-newsletters' ) : __( 'Continue', 'quintessential-newsletters' ), 'primary', 'submit', false ); ?>
				<a class="button-link" href="<?php echo esc_url( $semnews_skip_url ); ?>" style="margin-left:12px;"><?php esc_html_e( 'Skip for now', 'quintessential-newsletters' ); ?></a>
			</p>
		</form>
	</div>
</div>
