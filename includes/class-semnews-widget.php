<?php
/**
 * Classic widget wrapping the subscription form.
 *
 * @package SimpleEmailNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Newsletter signup widget.
 */
class SEMNEWS_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'semnews_widget',
			__( 'Newsletter Signup', 'quintessential-newsletters' ),
			array(
				'description' => __( 'A GDPR-friendly double opt-in newsletter signup form.', 'quintessential-newsletters' ),
			)
		);
	}

	/**
	 * Front-end output.
	 *
	 * @param array $args     Sidebar args.
	 * @param array $instance Saved settings.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$title       = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Subscribe to our newsletter', 'quintessential-newsletters' );
		$description = ! empty( $instance['description'] ) ? $instance['description'] : '';
		$show_name   = ! empty( $instance['show_name'] ) ? 'true' : 'false';

		echo wp_kses_post( $args['before_widget'] );

		$forms     = new SEMNEWS_Forms();
		$form_html = $forms->render_form(
			array(
				'title'       => $title,
				'description' => $description,
				'show_name'   => $show_name,
				'button'      => __( 'Subscribe', 'quintessential-newsletters' ),
				'source'      => 'widget',
			)
		);
		echo wp_kses( $form_html, semnews_allowed_form_html() );

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Settings form in the admin.
	 *
	 * @param array $instance Saved settings.
	 * @return void
	 */
	public function form( $instance ) {
		$title       = isset( $instance['title'] ) ? $instance['title'] : __( 'Subscribe to our newsletter', 'quintessential-newsletters' );
		$description = isset( $instance['description'] ) ? $instance['description'] : '';
		$show_name   = ! empty( $instance['show_name'] );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'quintessential-newsletters' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
				value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'description' ) ); ?>"><?php esc_html_e( 'Description:', 'quintessential-newsletters' ); ?></label>
			<textarea class="widefat" rows="3" id="<?php echo esc_attr( $this->get_field_id( 'description' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'description' ) ); ?>"><?php echo esc_textarea( $description ); ?></textarea>
		</p>
		<p>
			<input class="checkbox" type="checkbox" <?php checked( $show_name ); ?>
				id="<?php echo esc_attr( $this->get_field_id( 'show_name' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_name' ) ); ?>" />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_name' ) ); ?>"><?php esc_html_e( 'Show name field', 'quintessential-newsletters' ); ?></label>
		</p>
		<?php
	}

	/**
	 * Save settings.
	 *
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title'       => sanitize_text_field( $new_instance['title'] ?? '' ),
			'description' => sanitize_textarea_field( $new_instance['description'] ?? '' ),
			'show_name'   => ! empty( $new_instance['show_name'] ) ? 1 : 0,
		);
	}
}
