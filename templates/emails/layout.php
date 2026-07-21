<?php
/**
 * Shared HTML email layout.
 *
 * @package QuintessentialNewsletters
 * @var string $title     Email title.
 * @var string $content   Inner HTML content.
 * @var string $footer    Footer HTML (sender identity + unsubscribe).
 * @var string $preheader Optional inbox preview text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>"<?php echo is_rtl() ? ' dir="rtl"' : ''; ?>>
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<title><?php echo esc_html( $title ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;">
	<?php if ( ! empty( $preheader ) ) : ?>
		<?php
		// Inbox preview text. Invisible in the opened email BY DESIGN — mail
		// clients show it next to the subject in the message list. The style
		// stack is the industry-standard belt-and-braces hiding (Outlook needs
		// mso-hide; some clients ignore display:none alone in the preview).
		?>
		<div class="semnews-preheader" style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;"><?php echo esc_html( $preheader ); ?></div>
		<?php
		// Whitespace padding straight after the preheader so clients that fill
		// the preview snippet from the body don't append visible content to it.
		// SEMNEWS_Mailer::to_plain_text() strips this block from the text part.
		?>
		<div class="semnews-preheader-pad" style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;" aria-hidden="true"><?php echo str_repeat( '&nbsp;&zwnj;', 96 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static HTML entities. ?></div>
	<?php endif; ?>
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;">
		<tr>
			<td align="center" style="padding:24px 12px;">
				<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">
					<tr>
						<td style="padding:32px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1d2327;">
							<?php
							// Escaped at output through the email allowlist, which keeps
							// the inline styles and table attributes email clients need
							// (plain wp_kses_post would strip some of them).
							echo wp_kses( $content, semnews_allowed_email_html() );
							echo wp_kses( $footer, semnews_allowed_email_html() );
							?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
