<?php
/**
 * Campaign editor / sender screen.
 *
 * @package SimpleEmailNewsletters
 * @var object|null $campaign Existing campaign or null for new.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$semnews_campaign_id = $campaign ? (int) $campaign->id : 0;
$semnews_subject     = $campaign ? $campaign->subject : '';
$semnews_preheader   = $campaign ? $campaign->preheader : '';
$semnews_body        = $campaign ? $campaign->body : '';
$semnews_status      = $campaign ? $campaign->status : SEMNEWS_Campaigns::STATUS_DRAFT;
$semnews_is_sent      = ( SEMNEWS_Campaigns::STATUS_SENT === $semnews_status );
$semnews_is_sending   = ( SEMNEWS_Campaigns::STATUS_SENDING === $semnews_status );
$semnews_is_paused    = ( SEMNEWS_Campaigns::STATUS_PAUSED === $semnews_status );
$semnews_is_scheduled = ( SEMNEWS_Campaigns::STATUS_SCHEDULED === $semnews_status );
// No edits once sending has started (incl. paused): every batch must deliver identical content.
$semnews_is_locked    = ( $semnews_is_sent || $semnews_is_sending || $semnews_is_paused );

$semnews_recipients  = SEMNEWS_Subscribers::count_by_status( SEMNEWS_Subscribers::STATUS_SUBSCRIBED );

/**
 * Lets add-ons adjust the recipient count shown in the Send panel (e.g. the
 * Pro segment picker narrowing it to selected lists/tags).
 *
 * @param int         $semnews_recipients Confirmed-subscriber count.
 * @param object|null $campaign   Campaign row or null for new.
 */
$semnews_recipients = (int) apply_filters( 'semnews_campaign_recipient_count', $semnews_recipients, $campaign );
$semnews_list_url    = admin_url( 'admin.php?page=semnews-campaigns' );
$semnews_address_set = '' !== trim( (string) semnews_get_option( 'postal_address' ) );
$semnews_templates   = SEMNEWS_Templates::get_templates();
$semnews_categories  = get_categories( array( 'hide_empty' => false ) );
$semnews_lint        = $semnews_campaign_id ? SEMNEWS_Linter::lint_campaign( $semnews_subject, $semnews_body, $semnews_preheader ) : array();

// Remembered "Build from posts" choices, so the panel keeps state across reloads.
// The template prefers what this campaign was actually built with.
$semnews_build_prefs    = get_user_meta( get_current_user_id(), 'semnews_build_prefs', true );
$semnews_build_prefs    = is_array( $semnews_build_prefs ) ? $semnews_build_prefs : array();
$semnews_build_template = ( $campaign && $campaign->template ) ? $campaign->template : ( isset( $semnews_build_prefs['template'] ) ? $semnews_build_prefs['template'] : SEMNEWS_Templates::default_template() );
$semnews_build_count    = isset( $semnews_build_prefs['count'] ) ? max( 1, min( 50, (int) $semnews_build_prefs['count'] ) ) : 5;
$semnews_build_cats     = ( isset( $semnews_build_prefs['categories'] ) && is_array( $semnews_build_prefs['categories'] ) ) ? array_map( 'intval', $semnews_build_prefs['categories'] ) : array();
?>
<div class="wrap semnews-wrap">
	<h1 class="wp-heading-inline">
		<?php echo $semnews_campaign_id ? esc_html__( 'Edit newsletter', 'quintessential-newsletters' ) : esc_html__( 'Write newsletter', 'quintessential-newsletters' ); ?>
	</h1>
	<a href="<?php echo esc_url( $semnews_list_url ); ?>" class="page-title-action"><?php esc_html_e( 'Back to list', 'quintessential-newsletters' ); ?></a>
	<hr class="wp-header-end" />

	<?php SEMNEWS_Admin::render_notice(); ?>

	<?php if ( ! $semnews_address_set ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php esc_html_e( 'You need a physical postal address before you can send (it is legally required in every newsletter).', 'quintessential-newsletters' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=semnews-settings' ) ); ?>"><?php esc_html_e( 'Add it in Settings', 'quintessential-newsletters' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $semnews_is_sending || $semnews_is_paused ) : ?>
		<?php
		$semnews_counts     = SEMNEWS_Queue::counts( $semnews_campaign_id );
		$semnews_last_error = $semnews_counts['failed'] > 0 ? SEMNEWS_Queue::last_error( $semnews_campaign_id ) : '';
		?>
		<div class="notice notice-info inline">
			<p>
				<?php
				printf(
					/* translators: 1: sent, 2: total */
					esc_html__( 'Sending in progress: %1$s of %2$s delivered. This continues automatically in the background.', 'quintessential-newsletters' ),
					esc_html( number_format_i18n( $semnews_counts['sent'] ) ),
					esc_html( number_format_i18n( $campaign->total_recipients ) )
				);
				?>
				<?php if ( $semnews_is_paused ) : ?>
					<strong><?php esc_html_e( '(Paused)', 'quintessential-newsletters' ); ?></strong>
				<?php endif; ?>
			</p>
			<?php if ( $semnews_counts['failed'] > 0 ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: failed count */
						esc_html( _n( '%s send failed so far.', '%s sends failed so far.', $semnews_counts['failed'], 'quintessential-newsletters' ) ),
						esc_html( number_format_i18n( $semnews_counts['failed'] ) )
					);
					?>
					<?php if ( $semnews_last_error ) : ?>
						<code><?php echo esc_html( $semnews_last_error ); ?></code>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="semnews-columns semnews-editor">
		<div class="semnews-editor-main">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="semnews_save_campaign" />
				<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $semnews_campaign_id ); ?>" />
				<?php wp_nonce_field( 'semnews_save_campaign' ); ?>

				<p>
					<label for="semnews-subject"><strong><?php esc_html_e( 'Subject', 'quintessential-newsletters' ); ?></strong></label>
					<input type="text" id="semnews-subject" name="subject" class="large-text" value="<?php echo esc_attr( $semnews_subject ); ?>" <?php disabled( $semnews_is_locked ); ?> />
				</p>
				<p>
					<label for="semnews-preheader"><strong><?php esc_html_e( 'Preheader', 'quintessential-newsletters' ); ?></strong> <span class="description"><?php esc_html_e( '(shown next to the subject in the inbox list — deliberately invisible inside the opened email)', 'quintessential-newsletters' ); ?></span></label>
					<input type="text" id="semnews-preheader" name="preheader" class="large-text" value="<?php echo esc_attr( $semnews_preheader ); ?>" <?php disabled( $semnews_is_locked ); ?> />
				</p>
				<?php
				/**
				 * Lets add-ons render extra composer fields inside the save form
				 * (e.g. the Pro "Send as" sender-profile selector).
				 *
				 * @param object|null $campaign  Campaign row or null for new.
				 * @param bool        $semnews_is_locked Whether the campaign is read-only.
				 */
				do_action( 'semnews_campaign_editor_fields', $campaign, $semnews_is_locked );
				?>
				<p><label for="semnews-body"><strong><?php esc_html_e( 'Content', 'quintessential-newsletters' ); ?></strong></label></p>
				<?php
				if ( $semnews_is_locked ) {
					echo '<div class="semnews-body-readonly">' . wp_kses_post( $semnews_body ) . '</div>';
				} else {
					wp_editor(
						$semnews_body,
						'semnews-body',
						array(
							'textarea_name' => 'body',
							'textarea_rows' => 14,
							'media_buttons' => true,
						)
					);
				}
				?>
				<p class="description"><?php esc_html_e( 'Tip: you can use {{name}}, {{first_name}} and {{email}} to personalise honestly. Empty values render as nothing — never a fake "Hi Friend".', 'quintessential-newsletters' ); ?></p>

				<?php if ( ! $semnews_is_locked ) : ?>
					<p><?php submit_button( __( 'Save draft', 'quintessential-newsletters' ), 'secondary', 'submit', false ); ?></p>
				<?php endif; ?>
			</form>
		</div>

		<div class="semnews-editor-side">
			<?php if ( $semnews_campaign_id ) : ?>
				<div class="semnews-panel semnews-lint-panel">
					<h2><?php esc_html_e( 'Honesty check', 'quintessential-newsletters' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Friendly advice — it never blocks sending.', 'quintessential-newsletters' ); ?></p>
					<?php echo wp_kses_post( SEMNEWS_Linter::render_notice_html( $semnews_lint ) ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! $semnews_is_locked ) : ?>
				<div class="semnews-panel">
					<h2><?php esc_html_e( 'Build from posts', 'quintessential-newsletters' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Generate the content from your latest posts using a template. You can edit it afterwards. This replaces the content above.', 'quintessential-newsletters' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Replace the current content with posts? Save anything you want to keep first.', 'quintessential-newsletters' ) ); ?>');">
						<input type="hidden" name="action" value="semnews_build_campaign" />
						<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $semnews_campaign_id ); ?>" />
						<?php wp_nonce_field( 'semnews_build_campaign' ); ?>
						<p>
							<label for="semnews-build-template"><strong><?php esc_html_e( 'Template', 'quintessential-newsletters' ); ?></strong></label><br />
							<select id="semnews-build-template" name="template">
								<?php foreach ( $semnews_templates as $semnews_tpl_id => $semnews_tpl ) : ?>
									<?php if ( SEMNEWS_Templates::CUSTOM === $semnews_tpl_id ) { continue; } ?>
									<option value="<?php echo esc_attr( $semnews_tpl_id ); ?>" <?php selected( $semnews_build_template, $semnews_tpl_id ); ?>><?php echo esc_html( $semnews_tpl['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php
							// The initial href already points at the selected
							// template, so Preview works without JS (the script
							// only re-targets it when the selection changes).
							$semnews_tpl_preview_base = wp_nonce_url( add_query_arg( array( 'action' => 'semnews_preview_template' ), admin_url( 'admin-post.php' ) ), 'semnews_preview_template' );
							?>
							<a href="<?php echo esc_url( $semnews_tpl_preview_base . '&template=' . rawurlencode( $semnews_build_template ) ); ?>" id="semnews-template-preview" class="button" data-base="<?php echo esc_url( $semnews_tpl_preview_base ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview', 'quintessential-newsletters' ); ?></a>
						</p>
						<?php if ( ! semnews_pro_active() ) : ?>
							<div class="semnews-pro-note" style="margin:12px 0;">
								<p>
									<a class="semnews-pro-badge" href="<?php echo esc_url( SEMNEWS_Admin::upgrade_url() ); ?>"><?php esc_html_e( 'Pro', 'quintessential-newsletters' ); ?></a>
									<?php esc_html_e( 'A separate Pro add-on adds a template gallery — Cards, Magazine, Plain text, Announcement and Compact digest, plus write-your-own Custom HTML — each topped with your brand logo.', 'quintessential-newsletters' ); ?>
									<a href="<?php echo esc_url( SEMNEWS_Admin::upgrade_url() ); ?>"><?php esc_html_e( 'Learn more →', 'quintessential-newsletters' ); ?></a>
								</p>
							</div>
						<?php endif; ?>
						<p>
							<label for="semnews-build-count"><strong><?php esc_html_e( 'Number of posts', 'quintessential-newsletters' ); ?></strong></label><br />
							<input type="number" id="semnews-build-count" name="post_count" min="1" max="50" value="<?php echo esc_attr( $semnews_build_count ); ?>" class="small-text" />
						</p>
						<?php if ( $semnews_categories ) : ?>
							<p><strong><?php esc_html_e( 'Category', 'quintessential-newsletters' ); ?></strong></p>
							<fieldset class="semnews-checkbox-list semnews-checkbox-box">
								<?php foreach ( $semnews_categories as $semnews_cat ) : ?>
									<label>
										<input type="checkbox" name="categories[]" value="<?php echo esc_attr( $semnews_cat->term_id ); ?>" <?php checked( in_array( (int) $semnews_cat->term_id, $semnews_build_cats, true ) ); ?> />
										<?php echo esc_html( $semnews_cat->name ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Leave unchecked for all categories.', 'quintessential-newsletters' ); ?></p>
						<?php endif; ?>
						<?php submit_button( __( 'Build content', 'quintessential-newsletters' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			<?php endif; ?>

			<div class="semnews-panel">
				<h2><?php esc_html_e( 'Send', 'quintessential-newsletters' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: %s: recipient count */
						esc_html( _n( 'This will be sent to %s confirmed subscriber.', 'This will be sent to %s confirmed subscribers.', $semnews_recipients, 'quintessential-newsletters' ) ),
						'<strong>' . esc_html( number_format_i18n( $semnews_recipients ) ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);
					?>
				</p>

				<?php if ( $semnews_campaign_id && SEMNEWS_Campaigns::STATUS_DRAFT === $semnews_status ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Send this newsletter now to all confirmed subscribers?', 'quintessential-newsletters' ) ); ?>');">
						<input type="hidden" name="action" value="semnews_send_campaign" />
						<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $semnews_campaign_id ); ?>" />
						<?php wp_nonce_field( 'semnews_send_campaign' ); ?>
						<button type="submit" class="button button-primary button-hero" <?php disabled( ! $semnews_address_set || $semnews_recipients < 1 ); ?>>
							<?php esc_html_e( 'Send now', 'quintessential-newsletters' ); ?>
						</button>
					</form>

					<?php
					/**
					 * Lets add-ons extend the Send panel for a sendable draft
					 * (e.g. the Pro "Schedule for later" form).
					 *
					 * @param object $campaign    Campaign row.
					 * @param bool   $can_send    Whether sending is currently possible.
					 */
					do_action( 'semnews_campaign_send_panel', $campaign, ( $semnews_address_set && $semnews_recipients > 0 ) );
					?>
				<?php elseif ( $semnews_is_scheduled ) : ?>
					<p>
						<?php
						printf(
							/* translators: %s: scheduled date/time in site timezone. */
							esc_html__( 'Scheduled for %s.', 'quintessential-newsletters' ),
							'<strong>' . esc_html( get_date_from_gmt( (string) $campaign->scheduled_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						);
						?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="semnews_unschedule_campaign" />
						<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $semnews_campaign_id ); ?>" />
						<?php wp_nonce_field( 'semnews_unschedule_campaign' ); ?>
						<?php submit_button( __( 'Cancel schedule', 'quintessential-newsletters' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php elseif ( $semnews_is_sending ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="semnews_pause_campaign" />
						<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $semnews_campaign_id ); ?>" />
						<?php wp_nonce_field( 'semnews_pause_campaign' ); ?>
						<?php submit_button( __( 'Pause sending', 'quintessential-newsletters' ), 'secondary', 'submit', false ); ?>
					</form>
					<p class="description"><?php esc_html_e( 'The batch currently in flight finishes; no new batches start until you resume.', 'quintessential-newsletters' ); ?></p>
				<?php elseif ( $semnews_is_paused ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="semnews_resume_campaign" />
						<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $semnews_campaign_id ); ?>" />
						<?php wp_nonce_field( 'semnews_resume_campaign' ); ?>
						<?php submit_button( __( 'Resume sending', 'quintessential-newsletters' ), 'primary', 'submit', false ); ?>
					</form>
					<p class="description"><?php esc_html_e( 'Picks up exactly where it left off — nobody receives the newsletter twice.', 'quintessential-newsletters' ); ?></p>
				<?php elseif ( $semnews_is_sent ) : ?>
					<p class="semnews-sent-badge"><?php esc_html_e( 'This newsletter has been sent.', 'quintessential-newsletters' ); ?></p>
				<?php elseif ( ! $semnews_campaign_id ) : ?>
					<p class="description"><?php esc_html_e( 'Save the draft to enable sending and test emails.', 'quintessential-newsletters' ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( $semnews_campaign_id ) : ?>
				<div class="semnews-panel">
					<h2><?php esc_html_e( 'Preview & duplicate', 'quintessential-newsletters' ); ?></h2>
					<?php $semnews_preview_url = wp_nonce_url( add_query_arg( array( 'action' => 'semnews_preview_campaign', 'campaign' => $semnews_campaign_id ), admin_url( 'admin-post.php' ) ), 'semnews_preview_campaign' ); ?>
					<p><a class="button" href="<?php echo esc_url( $semnews_preview_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview in browser', 'quintessential-newsletters' ); ?></a></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="semnews_duplicate_campaign" />
						<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $semnews_campaign_id ); ?>" />
						<?php wp_nonce_field( 'semnews_duplicate_campaign' ); ?>
						<?php submit_button( __( 'Duplicate as new draft', 'quintessential-newsletters' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			<?php endif; ?>

			<?php if ( $semnews_campaign_id && ! $semnews_is_sent ) : ?>
				<div class="semnews-panel">
					<h2><?php esc_html_e( 'Send a test', 'quintessential-newsletters' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="semnews_send_test" />
						<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $semnews_campaign_id ); ?>" />
						<?php wp_nonce_field( 'semnews_send_test' ); ?>
						<p>
							<input type="email" name="test_email" class="regular-text" placeholder="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" />
						</p>
						<?php submit_button( __( 'Send test', 'quintessential-newsletters' ), 'secondary', 'submit', false ); ?>
						<p class="description"><?php esc_html_e( 'Test emails are not tied to a real subscriber, so their unsubscribe/preference links are placeholders — clicking one explains this. They work normally in real sends.', 'quintessential-newsletters' ); ?></p>
					</form>
				</div>

				<div class="semnews-panel">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this newsletter? This cannot be undone.', 'quintessential-newsletters' ) ); ?>');">
						<input type="hidden" name="action" value="semnews_delete_campaign" />
						<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $semnews_campaign_id ); ?>" />
						<?php wp_nonce_field( 'semnews_delete_campaign' ); ?>
						<button type="submit" class="button-link delete"><?php esc_html_e( 'Delete newsletter', 'quintessential-newsletters' ); ?></button>
					</form>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
