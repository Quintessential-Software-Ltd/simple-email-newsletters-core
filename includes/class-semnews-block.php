<?php
/**
 * Gutenberg block registration (server-rendered, no build step required).
 *
 * @package QuintessentialNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block controller.
 */
class SEMNEWS_Block {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the block type and its editor script.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'semnews-block-editor',
			SEMNEWS_PLUGIN_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			SEMNEWS_VERSION,
			true
		);

		// Modern registration: metadata lives in blocks/subscribe/block.json;
		// only the PHP render callback is supplied here.
		register_block_type(
			SEMNEWS_PLUGIN_DIR . 'blocks/subscribe',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Server render callback — reuses the shortcode form renderer.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$forms = new SEMNEWS_Forms();

		return $forms->render_form(
			array(
				'title'       => isset( $attributes['title'] ) ? $attributes['title'] : '',
				'description' => isset( $attributes['description'] ) ? $attributes['description'] : '',
				'show_name'   => ! empty( $attributes['showName'] ) ? 'true' : 'false',
				'button'      => isset( $attributes['button'] ) ? $attributes['button'] : __( 'Subscribe', 'quintessential-newsletters' ),
				'source'      => 'block',
			)
		);
	}
}
