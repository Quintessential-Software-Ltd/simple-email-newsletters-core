<?php
/**
 * Welcome email body (sent after confirmation).
 *
 * @package SimpleEmailNewsletters
 * @var object $subscriber   Subscriber row.
 * @var string $company      Company / sender name.
 * @var string $custom_intro Optional owner-written intro (replaces the default paragraph).
 * @var string $header       Branded header HTML (logo or wordmark), may be ''.
 * @var string $site_url     Home URL for the call-to-action button.
 * @var string $site_name    Site title for the call-to-action button.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$semnews_greeting = $subscriber->name ? sprintf( /* translators: %s: name */ __( 'Hi %s,', 'quintessential-newsletters' ), $subscriber->name ) : __( 'Hi,', 'quintessential-newsletters' );
?>
<?php echo wp_kses( $header, semnews_allowed_email_html() ); ?>

<h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;color:#1d2327;"><?php esc_html_e( 'You’re in — welcome!', 'quintessential-newsletters' ); ?></h1>

<p style="margin:0 0 16px;"><?php echo esc_html( $semnews_greeting ); ?></p>

<?php if ( ! empty( $custom_intro ) ) : ?>
	<p style="margin:0 0 16px;"><?php echo nl2br( esc_html( $custom_intro ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped before nl2br. ?></p>
<?php else : ?>
	<p style="margin:0 0 16px;">
		<?php
		printf(
			/* translators: %s: company / sender name */
			esc_html__( 'Thanks for confirming your subscription to the %s newsletter — it’s great to have you.', 'quintessential-newsletters' ),
			'<strong>' . esc_html( $company ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		?>
	</p>
<?php endif; ?>

<p style="margin:0 0 8px;"><?php esc_html_e( 'While you wait for the first issue, the newest articles are already on the site:', 'quintessential-newsletters' ); ?></p>

<p style="text-align:center;margin:24px 0 28px;">
	<a href="<?php echo esc_url( $site_url ); ?>" style="display:inline-block;background:#2271b1;color:#ffffff;text-decoration:none;padding:13px 30px;border-radius:6px;font-size:16px;font-weight:600;">
		<?php
		/* translators: %s: site name. */
		printf( esc_html__( 'Read the latest from %s', 'quintessential-newsletters' ), esc_html( $site_name ) );
		?>
	</a>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 4px;">
	<tr>
		<td style="background:#f6f7f8;border-radius:6px;padding:14px 18px;font-size:13px;line-height:1.6;color:#50575e;">
			<?php esc_html_e( 'A promise: we only send what you signed up for, we never share your address, and every email includes a one-click unsubscribe link — no login, no questions.', 'quintessential-newsletters' ); ?>
		</td>
	</tr>
</table>
