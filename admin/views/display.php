<?php
/**
 * Display & placement screen.
 *
 * @package SimpleEmailNewsletters
 * @var array $config Current display configuration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$semnews_opt        = 'semnews_display';
$semnews_post_types = get_post_types( array( 'public' => true ), 'objects' );
$semnews_enabled_pt = (array) $config['content_post_types'];

?>
<div class="wrap semnews-wrap">
	<h1><?php esc_html_e( 'Display & Placement', 'quintessential-newsletters' ); ?></h1>
	<?php SEMNEWS_Admin::render_notice(); ?>

	<p class="description" style="max-width:48em;">
		<?php esc_html_e( 'Choose where your signup form appears. Every placement reuses your existing form and double opt-in, ships off by default, and is never shown to someone who has already subscribed.', 'quintessential-newsletters' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="semnews_save_display" />
		<?php wp_nonce_field( 'semnews_save_display' ); ?>

		<h2 class="title"><?php esc_html_e( 'Form text (shared by all placements)', 'quintessential-newsletters' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="d-title"><?php esc_html_e( 'Heading', 'quintessential-newsletters' ); ?></label></th>
				<td><input type="text" id="d-title" class="regular-text" name="<?php echo esc_attr( $semnews_opt ); ?>[title]" value="<?php echo esc_attr( $config['title'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="d-desc"><?php esc_html_e( 'Description', 'quintessential-newsletters' ); ?></label></th>
				<td><input type="text" id="d-desc" class="large-text" name="<?php echo esc_attr( $semnews_opt ); ?>[description]" value="<?php echo esc_attr( $config['description'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="d-button"><?php esc_html_e( 'Button label', 'quintessential-newsletters' ); ?></label></th>
				<td><input type="text" id="d-button" class="regular-text" name="<?php echo esc_attr( $semnews_opt ); ?>[button]" value="<?php echo esc_attr( $config['button'] ); ?>" /></td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'After / within post content', 'quintessential-newsletters' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable', 'quintessential-newsletters' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( $semnews_opt ); ?>[content_enabled]" value="1" <?php checked( $config['content_enabled'] ); ?> /> <?php esc_html_e( 'Show the form automatically inside posts', 'quintessential-newsletters' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><label for="d-cpos"><?php esc_html_e( 'Position', 'quintessential-newsletters' ); ?></label></th>
				<td>
					<select id="d-cpos" name="<?php echo esc_attr( $semnews_opt ); ?>[content_position]">
						<option value="after" <?php selected( $config['content_position'], 'after' ); ?>><?php esc_html_e( 'After the content', 'quintessential-newsletters' ); ?></option>
						<option value="before" <?php selected( $config['content_position'], 'before' ); ?>><?php esc_html_e( 'Before the content', 'quintessential-newsletters' ); ?></option>
						<option value="both" <?php selected( $config['content_position'], 'both' ); ?>><?php esc_html_e( 'Before and after', 'quintessential-newsletters' ); ?></option>
						<option value="paragraph" <?php selected( $config['content_position'], 'paragraph' ); ?>><?php esc_html_e( 'After a paragraph', 'quintessential-newsletters' ); ?></option>
					</select>
					<?php esc_html_e( 'after paragraph #', 'quintessential-newsletters' ); ?>
					<input type="number" min="1" max="50" class="small-text" name="<?php echo esc_attr( $semnews_opt ); ?>[content_paragraph]" value="<?php echo esc_attr( $config['content_paragraph'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show on', 'quintessential-newsletters' ); ?></th>
				<td>
					<fieldset class="semnews-checkbox-list">
						<?php foreach ( $semnews_post_types as $semnews_pt ) : ?>
							<?php if ( 'attachment' === $semnews_pt->name ) { continue; } ?>
							<label style="display:inline-block;margin:0 14px 4px 0;">
								<input type="checkbox" name="<?php echo esc_attr( $semnews_opt ); ?>[content_post_types][]" value="<?php echo esc_attr( $semnews_pt->name ); ?>" <?php checked( in_array( $semnews_pt->name, $semnews_enabled_pt, true ) ); ?> />
								<?php echo esc_html( $semnews_pt->labels->singular_name ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
		</table>

		<?php
		/**
		 * Lets add-ons render extra placement sections (e.g. the Pro overlays:
		 * popup, slide-in, sticky bar) inside this form.
		 *
		 * @param array  $config Current display configuration.
		 * @param string $semnews_opt    Option field prefix ('semnews_display').
		 */
		do_action( 'semnews_display_sections', $config, $semnews_opt );
		?>

		<?php submit_button( __( 'Save display settings', 'quintessential-newsletters' ) ); ?>
	</form>
</div>
