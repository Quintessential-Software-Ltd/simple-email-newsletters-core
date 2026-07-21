<?php
/**
 * Single-subscriber detail screen: full record + consent history (the plugin's
 * GDPR Art. 7 proof), with per-subscriber actions.
 *
 * @package QuintessentialNewsletters
 * @var object $subscriber Subscriber row.
 * @var array  $history    Consent log entries (newest first).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$semnews_statuses = SEMNEWS_Subscribers::statuses();
$semnews_bases    = SEMNEWS_Subscribers::bases();
$semnews_back_url = admin_url( 'admin.php?page=semnews-subscribers' );
$semnews_post_url = admin_url( 'admin-post.php' );

$semnews_fields = array(
	__( 'Email', 'quintessential-newsletters' )           => $subscriber->email,
	__( 'Name', 'quintessential-newsletters' )            => $subscriber->name ? $subscriber->name : '—',
	__( 'Status', 'quintessential-newsletters' )          => isset( $semnews_statuses[ $subscriber->status ] ) ? $semnews_statuses[ $subscriber->status ] : $subscriber->status,
	__( 'Lawful basis', 'quintessential-newsletters' )    => isset( $semnews_bases[ $subscriber->consent_basis ] ) ? $semnews_bases[ $subscriber->consent_basis ] : $subscriber->consent_basis,
	__( 'Source', 'quintessential-newsletters' )          => $subscriber->source ? $subscriber->source : '—',
	__( 'Subscribed at', 'quintessential-newsletters' )   => $subscriber->created_at,
	__( 'Confirmed at', 'quintessential-newsletters' )    => $subscriber->confirmed_at ? $subscriber->confirmed_at : '—',
	__( 'Unsubscribed at', 'quintessential-newsletters' ) => $subscriber->unsubscribed_at ? $subscriber->unsubscribed_at : '—',
	__( 'Signup IP', 'quintessential-newsletters' )       => $subscriber->ip_signup ? $subscriber->ip_signup : '—',
	__( 'Confirmation IP', 'quintessential-newsletters' ) => $subscriber->ip_confirmed ? $subscriber->ip_confirmed : '—',
	__( 'Consent text', 'quintessential-newsletters' )    => $subscriber->consent_text ? $subscriber->consent_text : '—',
	__( 'Consent version', 'quintessential-newsletters' ) => $subscriber->consent_version ? $subscriber->consent_version : '—',
);
?>
<div class="wrap semnews-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Subscriber', 'quintessential-newsletters' ); ?></h1>
	<a href="<?php echo esc_url( $semnews_back_url ); ?>" class="page-title-action"><?php esc_html_e( 'Back to list', 'quintessential-newsletters' ); ?></a>
	<hr class="wp-header-end" />

	<?php SEMNEWS_Admin::render_notice(); ?>

	<div class="semnews-columns semnews-editor">
		<div class="semnews-editor-main">
			<div class="semnews-panel">
				<h2><?php esc_html_e( 'Record', 'quintessential-newsletters' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<?php foreach ( $semnews_fields as $semnews_label => $semnews_value ) : ?>
							<tr>
								<th style="width:30%;"><?php echo esc_html( $semnews_label ); ?></th>
								<td><?php echo esc_html( $semnews_value ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="semnews-panel">
				<h2><?php esc_html_e( 'Consent history', 'quintessential-newsletters' ); ?></h2>
				<p class="description"><?php esc_html_e( 'The append-only trail used as proof of consent (GDPR Art. 7).', 'quintessential-newsletters' ); ?></p>
				<?php if ( empty( $history ) ) : ?>
					<p><?php esc_html_e( 'No events recorded.', 'quintessential-newsletters' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead><tr>
							<th><?php esc_html_e( 'When (UTC)', 'quintessential-newsletters' ); ?></th>
							<th><?php esc_html_e( 'Event', 'quintessential-newsletters' ); ?></th>
							<th><?php esc_html_e( 'Source', 'quintessential-newsletters' ); ?></th>
							<th><?php esc_html_e( 'IP', 'quintessential-newsletters' ); ?></th>
						</tr></thead>
						<tbody>
							<?php foreach ( $history as $semnews_event ) : ?>
								<tr>
									<td><?php echo esc_html( $semnews_event['created_at'] ); ?></td>
									<td><code><?php echo esc_html( $semnews_event['event'] ); ?></code></td>
									<td><?php echo esc_html( $semnews_event['source'] ? $semnews_event['source'] : '—' ); ?></td>
									<td><?php echo esc_html( $semnews_event['ip'] ? $semnews_event['ip'] : '—' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<div class="semnews-editor-side">
			<div class="semnews-panel">
				<h2><?php esc_html_e( 'Actions', 'quintessential-newsletters' ); ?></h2>

				<?php if ( SEMNEWS_Subscribers::STATUS_PENDING === $subscriber->status ) : ?>
					<form method="post" action="<?php echo esc_url( $semnews_post_url ); ?>" style="margin-bottom:8px;">
						<input type="hidden" name="action" value="semnews_subscriber_action" />
						<input type="hidden" name="do" value="resend" />
						<input type="hidden" name="subscriber_id" value="<?php echo esc_attr( $subscriber->id ); ?>" />
						<?php wp_nonce_field( 'semnews_subscriber_action_' . $subscriber->id ); ?>
						<?php submit_button( __( 'Resend confirmation email', 'quintessential-newsletters' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>

				<?php if ( SEMNEWS_Subscribers::STATUS_SUBSCRIBED === $subscriber->status ) : ?>
					<form method="post" action="<?php echo esc_url( $semnews_post_url ); ?>" style="margin-bottom:8px;">
						<input type="hidden" name="action" value="semnews_subscriber_action" />
						<input type="hidden" name="do" value="unsubscribe" />
						<input type="hidden" name="subscriber_id" value="<?php echo esc_attr( $subscriber->id ); ?>" />
						<?php wp_nonce_field( 'semnews_subscriber_action_' . $subscriber->id ); ?>
						<?php submit_button( __( 'Unsubscribe', 'quintessential-newsletters' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>

				<?php
			/**
			 * Lets add-ons render extra panels/forms on the subscriber view
			 * (e.g. the Pro list & tag membership editor).
			 *
			 * @param object $subscriber Subscriber row.
			 */
			do_action( 'semnews_subscriber_view_panels', $subscriber );
			?>

			<form method="post" action="<?php echo esc_url( $semnews_post_url ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Permanently delete this subscriber and their consent history? This cannot be undone.', 'quintessential-newsletters' ) ); ?>');">
					<input type="hidden" name="action" value="semnews_subscriber_action" />
					<input type="hidden" name="do" value="delete" />
					<input type="hidden" name="subscriber_id" value="<?php echo esc_attr( $subscriber->id ); ?>" />
					<?php wp_nonce_field( 'semnews_subscriber_action_' . $subscriber->id ); ?>
					<button type="submit" class="button-link delete"><?php esc_html_e( 'Delete subscriber', 'quintessential-newsletters' ); ?></button>
				</form>
			</div>
		</div>
	</div>
</div>
