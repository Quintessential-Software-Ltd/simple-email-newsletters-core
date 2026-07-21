<?php
/**
 * Double opt-in confirmation email body.
 *
 * @package QuintessentialNewsletters
 * @var object $subscriber   Subscriber row.
 * @var string $confirm_url  Tokenised confirmation URL.
 * @var string $company      Company / sender name.
 * @var string $custom_intro Optional owner-written intro (replaces the default paragraph).
 * @var string $header       Branded header HTML (logo or wordmark), may be ''.
 * @var string $site_url     Home URL, shown so recipients can verify the request source.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$semnews_greeting = $subscriber->name ? sprintf( /* translators: %s: name */ __( 'Hi %s,', 'quintessential-newsletters' ), $subscriber->name ) : __( 'Hi,', 'quintessential-newsletters' );
?>
<?php echo wp_kses( $header, semnews_allowed_email_html() ); ?>

<h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;color:#1d2327;"><?php esc_html_e( 'One click and you’re on the list', 'quintessential-newsletters' ); ?></h1>

<p style="margin:0 0 16px;"><?php echo esc_html( $semnews_greeting ); ?></p>

<?php if ( ! empty( $custom_intro ) ) : ?>
	<p style="margin:0 0 16px;"><?php echo nl2br( esc_html( $custom_intro ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped before nl2br. ?></p>
<?php else : ?>
	<p style="margin:0 0 16px;">
		<?php
		printf(
			/* translators: %s: company / sender name */
			esc_html__( 'Thanks for signing up to the %s newsletter. Please confirm your email address below — we only add you once you confirm.', 'quintessential-newsletters' ),
			'<strong>' . esc_html( $company ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		?>
	</p>
<?php endif; ?>

<p style="text-align:center;margin:28px 0;">
	<a href="<?php echo esc_url( $confirm_url ); ?>" style="display:inline-block;background:#2271b1;color:#ffffff;text-decoration:none;padding:13px 30px;border-radius:6px;font-size:16px;font-weight:600;">
		<?php esc_html_e( 'Confirm my subscription', 'quintessential-newsletters' ); ?>
	</a>
</p>

<p style="font-size:13px;color:#666;margin:0 0 12px;">
	<?php esc_html_e( 'If the button does not work, copy and paste this link into your browser:', 'quintessential-newsletters' ); ?><br />
	<a href="<?php echo esc_url( $confirm_url ); ?>" style="color:#2271b1;word-break:break-all;"><?php echo esc_html( $confirm_url ); ?></a>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0 4px;">
	<tr>
		<td style="background:#f6f7f8;border-radius:6px;padding:14px 18px;font-size:13px;line-height:1.6;color:#50575e;">
			<?php
			printf(
				/* translators: %s: site URL. */
				esc_html__( 'This request was made on %s. If it wasn’t you, simply ignore this email — you will not be added to the list and we will not email you again.', 'quintessential-newsletters' ),
				'<a href="' . esc_url( $site_url ) . '" style="color:#50575e;">' . esc_html( $site_url ) . '</a>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
			?>
		</td>
	</tr>
</table>
