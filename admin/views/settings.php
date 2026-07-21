<?php
/**
 * Settings screen.
 *
 * @package SimpleEmailNewsletters
 * @var array $settings Current settings (merged with defaults).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$semnews_opt = 'semnews_settings';
?>
<div class="wrap semnews-wrap">
	<h1><?php esc_html_e( 'Newsletter Settings', 'quintessential-newsletters' ); ?></h1>
	<?php SEMNEWS_Admin::render_notice(); ?>

	<form method="post" action="options.php">
		<?php settings_fields( SEMNEWS_Settings::OPTION_GROUP ); ?>

		<h2 class="title"><?php esc_html_e( 'Sender', 'quintessential-newsletters' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="semnews-from-name"><?php esc_html_e( 'From name', 'quintessential-newsletters' ); ?></label></th>
				<td><input type="text" id="semnews-from-name" name="<?php echo esc_attr( $semnews_opt ); ?>[from_name]" class="regular-text" value="<?php echo esc_attr( $settings['from_name'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="semnews-from-email"><?php esc_html_e( 'From email', 'quintessential-newsletters' ); ?></label></th>
				<td>
					<input type="email" id="semnews-from-email" name="<?php echo esc_attr( $semnews_opt ); ?>[from_email]" class="regular-text" value="<?php echo esc_attr( $settings['from_email'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Use an address on your own domain for the best deliverability (e.g. newsletter@yourdomain.com).', 'quintessential-newsletters' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="semnews-reply-to"><?php esc_html_e( 'Reply-to email', 'quintessential-newsletters' ); ?></label></th>
				<td>
					<input type="email" id="semnews-reply-to" name="<?php echo esc_attr( $semnews_opt ); ?>[reply_to]" class="regular-text" value="<?php echo esc_attr( $settings['reply_to'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Use a real inbox a person reads — honest newsletters reply to real people.', 'quintessential-newsletters' ); ?></p>
				</td>
			</tr>
			<?php
			/**
			 * Lets add-ons append rows to the Sender section (e.g. the Pro
			 * brand logo used by the gallery templates).
			 *
			 * @param array  $settings Current settings.
			 * @param string $semnews_opt      Option field prefix.
			 */
			do_action( 'semnews_settings_sender_rows', $settings, $semnews_opt );
			?>
		</table>

		<h2 class="title"><?php esc_html_e( 'Identity & legal footer', 'quintessential-newsletters' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="semnews-company"><?php esc_html_e( 'Company / sender name', 'quintessential-newsletters' ); ?></label></th>
				<td><input type="text" id="semnews-company" name="<?php echo esc_attr( $semnews_opt ); ?>[company_name]" class="regular-text" value="<?php echo esc_attr( $settings['company_name'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="semnews-address"><?php esc_html_e( 'Physical postal address', 'quintessential-newsletters' ); ?> <span class="semnews-required">*</span></label></th>
				<td>
					<textarea id="semnews-address" name="<?php echo esc_attr( $semnews_opt ); ?>[postal_address]" rows="3" class="large-text"><?php echo esc_textarea( $settings['postal_address'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Shown in every email footer. A valid postal address is legally required and builds trust. Sending is blocked until this is set.', 'quintessential-newsletters' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="semnews-privacy"><?php esc_html_e( 'Privacy policy page', 'quintessential-newsletters' ); ?></label></th>
				<td>
					<?php
					// wp_dropdown_pages() is on PHPCS's printing-functions list, so it
					// flags its array args even with echo=0; $semnews_opt is the fixed option slug
					// and the label is a translation, and the returned markup is emitted
					// through wp_kses() below.
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
					$semnews_pages_dropdown = wp_dropdown_pages(
						array(
							'name'              => $semnews_opt . '[privacy_policy_page]',
							'id'                => 'semnews-privacy',
							'selected'          => (int) $settings['privacy_policy_page'],
							'show_option_none'  => __( '— None —', 'quintessential-newsletters' ),
							'option_none_value' => 0,
							'echo'              => 0,
						)
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
					echo wp_kses(
						$semnews_pages_dropdown,
						array(
							'select' => array(
								'name' => true,
								'id'   => true,
							),
							'option' => array(
								'value'    => true,
								'selected' => true,
								'class'    => true,
							),
						)
					);
					?>
					<p class="description"><?php esc_html_e( 'Linked next to the consent checkbox so people know what they are agreeing to.', 'quintessential-newsletters' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Opt-in & consent', 'quintessential-newsletters' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Double opt-in', 'quintessential-newsletters' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $semnews_opt ); ?>[double_optin]" value="1" <?php checked( $settings['double_optin'] ); ?> />
						<?php esc_html_e( 'Require email confirmation before subscribing (strongly recommended)', 'quintessential-newsletters' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'The honest, GDPR-friendly default. Only turn this off if you have another verifiable consent record.', 'quintessential-newsletters' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Welcome email', 'quintessential-newsletters' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $semnews_opt ); ?>[send_welcome]" value="1" <?php checked( $settings['send_welcome'] ); ?> />
						<?php esc_html_e( 'Send a welcome email after someone confirms', 'quintessential-newsletters' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="semnews-conf-subject"><?php esc_html_e( 'Confirmation email', 'quintessential-newsletters' ); ?></label></th>
				<td>
					<input type="text" id="semnews-conf-subject" name="<?php echo esc_attr( $semnews_opt ); ?>[confirmation_subject]" class="large-text" value="<?php echo esc_attr( $settings['confirmation_subject'] ); ?>" placeholder="<?php esc_attr_e( 'Subject — leave blank for the default. {company} is replaced.', 'quintessential-newsletters' ); ?>" />
					<textarea name="<?php echo esc_attr( $semnews_opt ); ?>[confirmation_intro]" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Intro paragraph — leave blank for the default. The confirm button is always included.', 'quintessential-newsletters' ); ?>"><?php echo esc_textarea( $settings['confirmation_intro'] ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="semnews-welcome-subject"><?php esc_html_e( 'Welcome email', 'quintessential-newsletters' ); ?></label></th>
				<td>
					<input type="text" id="semnews-welcome-subject" name="<?php echo esc_attr( $semnews_opt ); ?>[welcome_subject]" class="large-text" value="<?php echo esc_attr( $settings['welcome_subject'] ); ?>" placeholder="<?php esc_attr_e( 'Subject — leave blank for the default. {company} is replaced.', 'quintessential-newsletters' ); ?>" />
					<textarea name="<?php echo esc_attr( $semnews_opt ); ?>[welcome_intro]" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Intro paragraph — leave blank for the default.', 'quintessential-newsletters' ); ?>"><?php echo esc_textarea( $settings['welcome_intro'] ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="semnews-consent-text"><?php esc_html_e( 'Consent text', 'quintessential-newsletters' ); ?></label></th>
				<td>
					<input type="text" id="semnews-consent-text" name="<?php echo esc_attr( $semnews_opt ); ?>[consent_text]" class="large-text" value="<?php echo esc_attr( $settings['consent_text'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Shown next to the (never pre-ticked) consent checkbox. The exact wording is recorded with every signup as proof of consent.', 'quintessential-newsletters' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="semnews-success"><?php esc_html_e( 'Success message (double opt-in)', 'quintessential-newsletters' ); ?></label></th>
				<td><input type="text" id="semnews-success" name="<?php echo esc_attr( $semnews_opt ); ?>[success_message]" class="large-text" value="<?php echo esc_attr( $settings['success_message'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="semnews-success-single"><?php esc_html_e( 'Success message (single opt-in)', 'quintessential-newsletters' ); ?></label></th>
				<td><input type="text" id="semnews-success-single" name="<?php echo esc_attr( $semnews_opt ); ?>[success_message_single]" class="large-text" value="<?php echo esc_attr( $settings['success_message_single'] ); ?>" /></td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Privacy & data', 'quintessential-newsletters' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="semnews-retention"><?php esc_html_e( 'Delete unconfirmed signups after', 'quintessential-newsletters' ); ?></label></th>
				<td>
					<input type="number" id="semnews-retention" name="<?php echo esc_attr( $semnews_opt ); ?>[retention_days]" min="0" value="<?php echo esc_attr( $settings['retention_days'] ); ?>" class="small-text" /> <?php esc_html_e( 'days', 'quintessential-newsletters' ); ?>
					<p class="description"><?php esc_html_e( 'Data minimisation: people who never confirm are automatically removed. Set to 0 to disable. Confirmed subscribers are never auto-deleted.', 'quintessential-newsletters' ); ?></p>
				</td>
			</tr>
			<?php
			/**
			 * Lets add-ons render extra rows in the Privacy & data section
			 * (e.g. the Pro public-archive toggle).
			 *
			 * @param array  $settings Current settings.
			 * @param string $semnews_opt      Option field prefix ('semnews_settings').
			 */
			do_action( 'semnews_settings_privacy_rows', $settings, $semnews_opt );
			?>
			<tr>
				<th scope="row"><?php esc_html_e( 'On uninstall', 'quintessential-newsletters' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $semnews_opt ); ?>[delete_data_on_uninstall]" value="1" <?php checked( $settings['delete_data_on_uninstall'] ); ?> />
						<?php esc_html_e( 'Delete all subscriber data if I uninstall this plugin', 'quintessential-newsletters' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Off by default so you never lose your list by accident.', 'quintessential-newsletters' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Sending', 'quintessential-newsletters' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="semnews-batch"><?php esc_html_e( 'Emails per batch', 'quintessential-newsletters' ); ?></label></th>
				<td>
					<input type="number" id="semnews-batch" name="<?php echo esc_attr( $semnews_opt ); ?>[batch_size]" min="1" max="500" value="<?php echo esc_attr( $settings['batch_size'] ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'How many emails to send per background run. Lower this if your host limits outgoing mail.', 'quintessential-newsletters' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Email service', 'quintessential-newsletters' ); ?></th>
				<td>
					<p class="description" style="max-width:46em;">
						<?php esc_html_e( 'Newsletters send through WordPress mail, so they work with any SMTP/email service. For reliable delivery at volume, install a free SMTP plugin (e.g. FluentSMTP or WP Mail SMTP) and connect a real provider — SendGrid, Amazon SES (cheapest at scale), Mailgun or Postmark. Then add that provider’s bounce/complaint webhook below so your list stays clean automatically.', 'quintessential-newsletters' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Bounces & complaints', 'quintessential-newsletters' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Webhook URL', 'quintessential-newsletters' ); ?></th>
				<td>
					<input type="text" class="large-text code" readonly onclick="this.select()" value="<?php echo esc_attr( SEMNEWS_Webhook::url() ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Add this URL to your email provider’s bounce/complaint webhook. The plugin auto-detects SendGrid, Amazon SES (SNS), Mailgun and Postmark — hard bounces and spam complaints go straight onto your suppression list. A custom service can also POST {"event":"bounce|complaint|soft_bounce","email":"…"}.', 'quintessential-newsletters' ); ?>
					</p>
					<p class="description">
						<strong><?php esc_html_e( 'Authenticate the request (pick one):', 'quintessential-newsletters' ); ?></strong><br />
						<?php esc_html_e( 'Preferred — send the request header', 'quintessential-newsletters' ); ?> <code>X-SEMNEWS-Secret: <?php echo esc_html( SEMNEWS_Webhook::secret() ); ?></code> <?php esc_html_e( '(or HTTP Basic Auth with the secret as the password) — this keeps the secret out of server logs.', 'quintessential-newsletters' ); ?><br />
						<?php esc_html_e( 'Fallback — if your provider can only set a URL, append', 'quintessential-newsletters' ); ?> <code>?secret=<?php echo esc_html( SEMNEWS_Webhook::secret() ); ?></code>. <em><?php esc_html_e( 'A secret in the URL can appear in server/proxy logs.', 'quintessential-newsletters' ); ?></em>
					</p>
					<details style="margin-top:8px;">
						<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e( 'Provider setup (which events to enable)', 'quintessential-newsletters' ); ?></summary>
						<ul style="margin:8px 0 0 18px;list-style:disc;">
							<li><strong>SendGrid</strong> — <?php esc_html_e( 'Settings → Mail Settings → Event Webhook: paste the URL and enable Bounced, Dropped and Spam Reports.', 'quintessential-newsletters' ); ?></li>
							<li><strong>Amazon SES</strong> — <?php esc_html_e( 'Send Bounce + Complaint notifications to an SNS topic, then add an HTTPS subscription with this URL (it auto-confirms).', 'quintessential-newsletters' ); ?></li>
							<li><strong>Mailgun</strong> — <?php esc_html_e( 'Sending → Webhooks: add the URL for Permanent Failure and Spam Complaints (Temporary Failure optional).', 'quintessential-newsletters' ); ?></li>
							<li><strong>Postmark</strong> — <?php esc_html_e( 'Servers → your server → Webhooks: add the URL and check Bounce and Spam Complaint.', 'quintessential-newsletters' ); ?></li>
						</ul>
					</details>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Webhook secret', 'quintessential-newsletters' ); ?></th>
				<td>
					<input type="text" class="regular-text code" readonly onclick="this.select()" value="<?php echo esc_attr( SEMNEWS_Webhook::secret() ); ?>" />
					<p class="description"><?php esc_html_e( 'Keep this private. Rotate it (below) if it is ever exposed.', 'quintessential-newsletters' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Rotate the webhook secret? You will need to update it in your mail provider.', 'quintessential-newsletters' ) ); ?>');">
		<input type="hidden" name="action" value="semnews_rotate_webhook" />
		<?php wp_nonce_field( 'semnews_rotate_webhook' ); ?>
		<?php submit_button( __( 'Rotate webhook secret', 'quintessential-newsletters' ), 'secondary', 'submit', true ); ?>
	</form>
</div>
