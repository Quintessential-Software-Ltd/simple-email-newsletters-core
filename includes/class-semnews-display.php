<?php
/**
 * Signup form placements: after/within post content.
 *
 * Every placement reuses the existing form (and therefore the existing
 * double-opt-in, consent logging and spam protection) — this class only decides
 * WHERE and WHEN the form appears. Honest by default: placements ship OFF and
 * nothing is shown to someone who has already subscribed.
 *
 * The Pro add-on extends this class with overlay placements (popup, slide-in,
 * sticky bar) via the semnews_display_defaults / semnews_display_sanitize filters and
 * the semnews_display_sections action on the settings screen.
 *
 * @package QuintessentialNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display / placement controller.
 */
class SEMNEWS_Display {

	/**
	 * Option holding the display configuration.
	 */
	const OPTION = 'semnews_display';

	/**
	 * Cookie set once a visitor subscribes, so placements stop showing for them.
	 */
	const SUBSCRIBED_COOKIE = 'semnews_subscribed';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'the_content', array( $this, 'inject_into_content' ), 20 );
	}

	/**
	 * Default configuration.
	 *
	 * @return array
	 */
	public static function defaults() {
		/**
		 * Lets add-ons register defaults for their own placements (e.g. the Pro
		 * overlays) so get_config() always returns a complete array.
		 *
		 * @param array $defaults Default display configuration.
		 */
		return apply_filters(
			'semnews_display_defaults',
			array(
				// Shared copy.
				'title'            => __( 'Subscribe to our newsletter', 'quintessential-newsletters' ),
				'description'      => __( 'Honest updates, straight to your inbox. Unsubscribe any time.', 'quintessential-newsletters' ),
				'button'           => __( 'Subscribe', 'quintessential-newsletters' ),

				// After/within post content.
				'content_enabled'  => 0,
				'content_position' => 'after', // after | before | both | paragraph.
				'content_paragraph'=> 3,
				'content_post_types' => array( 'post' ),
			)
		);
	}

	/**
	 * Merged configuration.
	 *
	 * @return array
	 */
	public static function get_config() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Sanitise and store submitted configuration.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function save_config( $input ) {
		$d     = self::defaults();
		$input = is_array( $input ) ? $input : array();
		$out   = array();

		$out['title']       = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : $d['title'];
		$out['description'] = isset( $input['description'] ) ? sanitize_text_field( $input['description'] ) : $d['description'];
		$out['button']      = isset( $input['button'] ) ? sanitize_text_field( $input['button'] ) : $d['button'];

		$out['content_enabled']  = empty( $input['content_enabled'] ) ? 0 : 1;
		$out['content_position'] = ( isset( $input['content_position'] ) && in_array( $input['content_position'], array( 'after', 'before', 'both', 'paragraph' ), true ) ) ? $input['content_position'] : $d['content_position'];
		$out['content_paragraph']= isset( $input['content_paragraph'] ) ? max( 1, min( 50, absint( $input['content_paragraph'] ) ) ) : $d['content_paragraph'];

		$types = isset( $input['content_post_types'] ) ? (array) $input['content_post_types'] : array();
		$types = array_values( array_filter( array_map( 'sanitize_key', $types ), 'post_type_exists' ) );
		$out['content_post_types'] = $types ? $types : array( 'post' );

		/**
		 * Lets add-ons sanitise and persist their own placement keys.
		 *
		 * @param array $out   Sanitised configuration so far.
		 * @param array $input Raw submitted values.
		 */
		$out = apply_filters( 'semnews_display_sanitize', $out, $input );

		update_option( self::OPTION, $out );
		return $out;
	}

	/**
	 * Whether this visitor has already subscribed (so we stop pestering them).
	 *
	 * @return bool
	 */
	public static function visitor_subscribed() {
		return ! empty( $_COOKIE[ self::SUBSCRIBED_COOKIE ] );
	}

	/**
	 * Build a compact inline form for a given placement.
	 *
	 * Protected rather than private so the Pro add-on's overlay controller can
	 * extend this class and reuse the exact same form (and its protections).
	 *
	 * @param string $source    Source label.
	 * @param bool   $show_name Whether to include the name field.
	 * @return string
	 */
	protected function form_html( $source, $show_name = false ) {
		$c = self::get_config();

		if ( ! semnews() || ! semnews()->forms ) {
			return '';
		}

		return semnews()->forms->render_form(
			array(
				'title'       => $c['title'],
				'description' => $c['description'],
				'button'      => $c['button'],
				'show_name'   => $show_name ? 'true' : 'false',
				'source'      => $source,
			)
		);
	}

	/**
	 * Append/prepend/insert the form into single post content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function inject_into_content( $content ) {
		$c = self::get_config();

		if ( empty( $c['content_enabled'] ) ) {
			return $content;
		}
		// Only the main content of a single, in-loop, main-query post.
		if ( is_admin() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! is_singular( (array) $c['content_post_types'] ) ) {
			return $content;
		}
		if ( self::visitor_subscribed() ) {
			return $content;
		}

		/** This filter is documented in this method. Lets sites suppress per-post. */
		if ( ! apply_filters( 'semnews_show_content_form', true, get_the_ID() ) ) {
			return $content;
		}

		$form = '<div class="semnews-inline-placement">' . $this->form_html( 'after-post', true ) . '</div>';

		switch ( $c['content_position'] ) {
			case 'before':
				return $form . $content;
			case 'both':
				return $form . $content . $form;
			case 'paragraph':
				return $this->insert_after_paragraph( $content, $form, (int) $c['content_paragraph'] );
			case 'after':
			default:
				return $content . $form;
		}
	}

	/**
	 * Insert HTML after the Nth top-level paragraph, falling back to append.
	 *
	 * @param string $content Content.
	 * @param string $insert  HTML to insert.
	 * @param int    $n       Paragraph number.
	 * @return string
	 */
	protected function insert_after_paragraph( $content, $insert, $n ) {
		$closing = '</p>';
		$paras   = explode( $closing, $content );

		if ( count( $paras ) - 1 < $n ) {
			return $content . $insert; // Not enough paragraphs — append.
		}

		$out = '';
		foreach ( $paras as $i => $para ) {
			$out .= $para;
			// Re-add the closing tag we split on (except after the last fragment).
			if ( $i < count( $paras ) - 1 ) {
				$out .= $closing;
			}
			if ( (int) $i === $n - 1 ) {
				$out .= $insert;
			}
		}
		return $out;
	}

}
