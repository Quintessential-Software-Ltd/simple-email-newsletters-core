<?php
/**
 * Subscribers screen.
 *
 * @package QuintessentialNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( SEMNEWS_Admin::capability() ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'quintessential-newsletters' ) );
}

$semnews_table = new SEMNEWS_Subscribers_List_Table();
$semnews_table->prepare_items();

// Nonce-verified and whitelisted in SEMNEWS_Subscribers_List_Table::request_filters().
$semnews_filters       = SEMNEWS_Subscribers_List_Table::request_filters();
$semnews_status_filter = $semnews_filters['status'];
$semnews_export_url    = wp_nonce_url(
	add_query_arg(
		array(
			'action' => 'semnews_export_csv',
			'status' => $semnews_status_filter,
		),
		admin_url( 'admin-post.php' )
	),
	'semnews_export_csv'
);
?>
<div class="wrap semnews-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Subscribers', 'quintessential-newsletters' ); ?></h1>
	<a href="<?php echo esc_url( $semnews_export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'quintessential-newsletters' ); ?></a>
	<?php $semnews_consent_export_url = wp_nonce_url( add_query_arg( 'action', 'semnews_export_consent_csv', admin_url( 'admin-post.php' ) ), 'semnews_export_consent_csv' ); ?>
	<a href="<?php echo esc_url( $semnews_consent_export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export consent register', 'quintessential-newsletters' ); ?></a>
	<hr class="wp-header-end" />

	<?php SEMNEWS_Admin::render_notice(); ?>

	<form method="get">
		<input type="hidden" name="page" value="semnews-subscribers" />
		<?php wp_nonce_field( 'semnews_filter_subscribers', '_semnews_filter', false ); ?>
		<?php if ( $semnews_status_filter ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $semnews_status_filter ); ?>" />
		<?php endif; ?>
		<?php $semnews_table->search_box( __( 'Search subscribers', 'quintessential-newsletters' ), 'semnews-subscriber' ); ?>
	</form>

	<form method="post">
		<input type="hidden" name="page" value="semnews-subscribers" />
		<?php wp_nonce_field( 'semnews_filter_subscribers', '_semnews_filter', false ); ?>
		<?php if ( $semnews_status_filter ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $semnews_status_filter ); ?>" />
		<?php endif; ?>
		<?php $semnews_table->display(); ?>
	</form>

	<div class="semnews-columns">
		<div class="semnews-panel semnews-panel-add">
			<h2><?php esc_html_e( 'Add a subscriber', 'quintessential-newsletters' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="semnews_add_subscriber" />
				<?php wp_nonce_field( 'semnews_add_subscriber' ); ?>
				<p>
					<label for="semnews-add-email"><?php esc_html_e( 'Email', 'quintessential-newsletters' ); ?></label><br />
					<input type="email" id="semnews-add-email" name="email" class="regular-text" required />
				</p>
				<p>
					<label for="semnews-add-name"><?php esc_html_e( 'Name (optional)', 'quintessential-newsletters' ); ?></label><br />
					<input type="text" id="semnews-add-name" name="name" class="regular-text" />
				</p>
				<p>
					<label for="semnews-add-status"><?php esc_html_e( 'Status', 'quintessential-newsletters' ); ?></label><br />
					<select id="semnews-add-status" name="status">
						<option value="subscribed"><?php esc_html_e( 'Subscribed (confirmed)', 'quintessential-newsletters' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Pending (send confirmation)', 'quintessential-newsletters' ); ?></option>
					</select>
				</p>
				<p>
					<label for="semnews-add-basis"><?php esc_html_e( 'Lawful basis', 'quintessential-newsletters' ); ?></label><br />
					<select id="semnews-add-basis" name="basis">
						<?php foreach ( SEMNEWS_Subscribers::bases() as $semnews_value => $semnews_label ) : ?>
							<option value="<?php echo esc_attr( $semnews_value ); ?>"><?php echo esc_html( $semnews_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="semnews-softoptin-note">
					<label>
						<input type="checkbox" name="soft_optin_attest" value="1" />
						<?php esc_html_e( 'For soft opt-in only: I confirm these people are existing customers who bought a similar product/service, were given a clear chance to opt out when we collected their details, and are offered an easy opt-out in every message (PECR reg. 22(3)).', 'quintessential-newsletters' ); ?>
					</label>
				</p>
				<p class="description"><?php esc_html_e( 'Only add people who have genuinely asked to hear from you (or qualify for soft opt-in). Adding someone as confirmed counts towards your subscriber limit.', 'quintessential-newsletters' ); ?></p>
				<?php submit_button( __( 'Add subscriber', 'quintessential-newsletters' ) ); ?>
			</form>
		</div>

		<div class="semnews-panel semnews-panel-import">
			<h2><?php esc_html_e( 'Import', 'quintessential-newsletters' ); ?></h2>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="semnews_import_csv" />
				<?php wp_nonce_field( 'semnews_import_csv' ); ?>
				<p>
					<label for="semnews-import"><?php esc_html_e( 'One per line: email,name', 'quintessential-newsletters' ); ?></label>
					<textarea id="semnews-import" name="semnews_import" rows="6" class="large-text code" placeholder="jane@example.com,Jane Doe"></textarea>
				</p>
				<p>
					<label for="semnews-import-file"><?php esc_html_e( 'Or upload a CSV file (same format)', 'quintessential-newsletters' ); ?></label><br />
					<input type="file" id="semnews-import-file" name="semnews_import_file" accept=".csv,text/csv,text/plain" />
				</p>
				<p>
					<label>
						<input type="radio" name="import_status" value="subscribed" checked />
						<?php esc_html_e( 'These people already gave consent (import as confirmed)', 'quintessential-newsletters' ); ?>
					</label><br />
					<label>
						<input type="radio" name="import_status" value="pending" />
						<?php esc_html_e( 'Send each a confirmation email (recommended)', 'quintessential-newsletters' ); ?>
					</label>
				</p>
				<p>
					<label for="semnews-import-basis"><?php esc_html_e( 'Lawful basis', 'quintessential-newsletters' ); ?></label><br />
					<select id="semnews-import-basis" name="basis">
						<?php foreach ( SEMNEWS_Subscribers::bases() as $semnews_value => $semnews_label ) : ?>
							<option value="<?php echo esc_attr( $semnews_value ); ?>"><?php echo esc_html( $semnews_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="semnews-softoptin-note">
					<label>
						<input type="checkbox" name="soft_optin_attest" value="1" />
						<?php esc_html_e( 'For soft opt-in only: I confirm the PECR soft opt-in conditions are met for everyone in this list.', 'quintessential-newsletters' ); ?>
					</label>
				</p>
				<p class="description"><?php esc_html_e( 'Never import bought or scraped lists. Previously unsubscribed or erased addresses are skipped automatically.', 'quintessential-newsletters' ); ?></p>
				<?php submit_button( __( 'Import', 'quintessential-newsletters' ), 'secondary' ); ?>
			</form>
		</div>
	</div>
</div>
