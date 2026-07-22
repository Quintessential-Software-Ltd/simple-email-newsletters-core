<?php
/**
 * Newsletter templates and post-to-HTML rendering.
 *
 * Turns a set of WordPress posts (filtered by category / count) into email-safe
 * HTML using one of several built-in layouts, or a site owner's own custom HTML
 * with simple, documented merge tags. The output is the inner body that
 * SEMNEWS_Mailer::wrap() then wraps in the shared layout (header + compliant footer),
 * and that SEMNEWS_Campaigns::sanitize_body() runs through wp_kses before storage —
 * so nothing here is trusted blindly.
 *
 * @package QuintessentialNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template registry + renderers.
 */
class SEMNEWS_Templates {

	/**
	 * Template identifiers. Free registers Simple only; the designed gallery
	 * (Cards, Magazine, Plain text, and more) and the Custom HTML template
	 * live in the Pro add-on, registered through the semnews_templates /
	 * semnews_rendered_template filters. A stored template id that is not
	 * registered falls back to Simple, so nothing ever breaks when the
	 * add-on is absent. The CUSTOM constant (and its renderer below) stay
	 * here because the {posts} tag engine they share also powers the free
	 * automated digest.
	 */
	const SIMPLE = 'simple';
	const CUSTOM = 'custom';

	/**
	 * Available templates: id => label + description.
	 *
	 * @return array
	 */
	public static function get_templates() {
		$templates = array(
			self::SIMPLE => array(
				'label'       => __( 'Simple list', 'quintessential-newsletters' ),
				'description' => __( 'A clean single column: each post as a heading, short excerpt and a read-more link.', 'quintessential-newsletters' ),
			),
		);

		/**
		 * Filter the available newsletter templates.
		 *
		 * @param array $templates Template registry.
		 */
		return apply_filters( 'semnews_templates', $templates );
	}

	/**
	 * Whether a template id exists.
	 *
	 * @param string $id Template id.
	 * @return bool
	 */
	public static function exists( $id ) {
		return array_key_exists( $id, self::get_templates() );
	}

	/**
	 * Default template id.
	 *
	 * @return string
	 */
	public static function default_template() {
		return self::SIMPLE;
	}

	/**
	 * Query posts for a digest/newsletter.
	 *
	 * @param array $args {
	 *     @type int       $count      Max posts to include.
	 *     @type int[]     $categories Category term IDs (empty = all).
	 *     @type string    $post_type  Post type (default 'post').
	 *     @type string    $since      Optional GMT 'Y-m-d H:i:s'; only posts published after this.
	 *     @type string    $orderby    Order field (default 'date').
	 * }
	 * @return WP_Post[] Posts (may be empty).
	 */
	public static function get_posts( $args = array() ) {
		$defaults = array(
			'count'      => 5,
			'categories' => array(),
			'post_type'  => 'post',
			'since'      => '',
			'orderby'    => 'date',
		);
		$args = wp_parse_args( $args, $defaults );

		$count      = max( 1, min( 50, (int) $args['count'] ) );
		$post_type  = post_type_exists( $args['post_type'] ) ? $args['post_type'] : 'post';
		$categories = array_filter( array_map( 'absint', (array) $args['categories'] ) );

		$query_args = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => $count,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		if ( $categories && 'post' === $post_type ) {
			$query_args['category__in'] = $categories;
		}

		if ( ! empty( $args['since'] ) ) {
			$query_args['date_query'] = array(
				array(
					'column'    => 'post_date_gmt',
					'after'     => $args['since'],
					'inclusive' => false,
				),
			);
		}

		/**
		 * Filter the WP_Query args used to gather newsletter posts.
		 *
		 * @param array $query_args Query args.
		 * @param array $args       Original request args.
		 */
		$query_args = apply_filters( 'semnews_posts_query_args', $query_args, $args );

		$query = new WP_Query( $query_args );

		return $query->posts;
	}

	/**
	 * Render posts into inner email HTML using the chosen template.
	 *
	 * @param string    $template_id Template id.
	 * @param WP_Post[] $posts       Posts to render.
	 * @param array     $config      { intro, custom_html, read_more }.
	 * @return string Inner HTML (still passed through wp_kses on save).
	 */
	public static function render( $template_id, $posts, $config = array() ) {
		if ( ! self::exists( $template_id ) ) {
			$template_id = self::default_template();
		}

		$config = wp_parse_args(
			$config,
			array(
				'intro'       => '',
				'custom_html' => '',
				'read_more'   => __( 'Read more', 'quintessential-newsletters' ),
			)
		);

		if ( self::CUSTOM === $template_id ) {
			$html = self::render_custom( $posts, $config );
		} else {
			// Simple, or an add-on template id: render the built-in layout and
			// let the semnews_rendered_template filter below replace it (this is
			// also the graceful fallback when an add-on template goes away).
			$html = self::render_simple( $posts, $config );
		}

		/**
		 * Filter the rendered newsletter body before it is saved/sanitised.
		 *
		 * @param string    $html        Rendered HTML.
		 * @param string    $template_id Template id.
		 * @param WP_Post[] $posts       Posts.
		 * @param array     $config      Render config.
		 */
		return apply_filters( 'semnews_rendered_template', $html, $template_id, $posts, $config );
	}

	// -----------------------------------------------------------------
	// Per-post field helpers (each returns plain, already-escaped values).
	// -----------------------------------------------------------------

	/**
	 * A trimmed, plain-text excerpt for a post. Public so add-on templates
	 * (registered via the semnews_templates filter, rendered via the
	 * semnews_rendered_template filter) build on the same escaped per-post
	 * fields the built-in layouts use.
	 *
	 * @param WP_Post $post  Post.
	 * @param int     $words Word count.
	 * @return string
	 */
	public static function excerpt( $post, $words = 30 ) {
		if ( has_excerpt( $post ) ) {
			$text = get_the_excerpt( $post );
		} else {
			$text = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		}
		$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
		return wp_trim_words( $text, $words, '…' );
	}

	/**
	 * Featured image URL at a sensible size, or empty string.
	 *
	 * @param WP_Post $post Post.
	 * @param string  $size Image size.
	 * @return string
	 */
	public static function image_url( $post, $size = 'large' ) {
		$url = get_the_post_thumbnail_url( $post, $size );
		return $url ? esc_url( $url ) : '';
	}

	/**
	 * Intro paragraph block (optional), shared by the visual templates.
	 *
	 * @param array $config Render config.
	 * @return string
	 */
	public static function intro_block( $config ) {
		$intro = trim( (string) $config['intro'] );
		if ( '' === $intro ) {
			return '';
		}
		return '<p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#1d2327;">' . esc_html( $intro ) . '</p>';
	}

	// -----------------------------------------------------------------
	// Renderers.
	// -----------------------------------------------------------------

	/**
	 * Simple single-column list.
	 *
	 * @param WP_Post[] $posts  Posts.
	 * @param array     $config Config.
	 * @return string
	 */
	protected static function render_simple( $posts, $config ) {
		$out  = self::intro_block( $config );
		$more = esc_html( $config['read_more'] );

		foreach ( $posts as $post ) {
			$title = esc_html( get_the_title( $post ) );
			$url   = esc_url( get_permalink( $post ) );
			$date  = esc_html( get_the_date( '', $post ) );
			$body  = esc_html( self::excerpt( $post ) );

			$out .= '<div style="margin:0 0 28px;padding:0 0 28px;border-bottom:1px solid #ececec;">';
			$out .= '<h2 style="margin:0 0 6px;font-size:20px;line-height:1.3;"><a href="' . $url . '" style="color:#1d2327;text-decoration:none;">' . $title . '</a></h2>';
			$out .= '<div style="margin:0 0 10px;font-size:12px;color:#777;">' . $date . '</div>';
			$out .= '<p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#3c434a;">' . $body . '</p>';
			$out .= '<a href="' . $url . '" style="font-size:14px;color:#2271b1;text-decoration:none;font-weight:600;">' . $more . ' →</a>';
			$out .= '</div>';
		}

		return $out;
	}

	/**
	 * Render a site owner's custom HTML with merge tags + a {posts} loop.
	 *
	 * Supported global tags: {site_name}, {site_url}, {date}, {intro}.
	 * Inside {posts} … {/posts}: {post_title}, {post_url}, {post_excerpt},
	 * {post_image}, {post_date}, {post_author}.
	 * Subscriber tags ({{name}} etc.) are applied later at send time.
	 *
	 * @param WP_Post[] $posts  Posts.
	 * @param array     $config Config (expects custom_html).
	 * @return string
	 */
	protected static function render_custom( $posts, $config ) {
		$html = (string) $config['custom_html'];
		if ( '' === trim( $html ) ) {
			// Fall back to the simple template so a digest is never blank.
			return self::render_simple( $posts, $config );
		}

		// Expand the repeatable {posts}…{/posts} block.
		$html = preg_replace_callback(
			'/\{posts\}(.*?)\{\/posts\}/s',
			static function ( $matches ) use ( $posts ) {
				return self::expand_post_loop( $matches[1], $posts );
			},
			$html
		);

		$globals = array(
			'{site_name}' => esc_html( get_bloginfo( 'name' ) ),
			'{site_url}'  => esc_url( home_url( '/' ) ),
			'{date}'      => esc_html( date_i18n( get_option( 'date_format' ) ) ),
			'{intro}'     => esc_html( (string) $config['intro'] ),
		);

		return strtr( $html, $globals );
	}

	/**
	 * Repeat a per-post item template for each post, replacing the {post_*}
	 * merge tags. Public so the Pro add-on's digest engine can re-expand a saved
	 * newsletter's {posts}…{/posts} block with fresh posts on every digest run.
	 *
	 * @param string    $item_tpl The markup between {posts} and {/posts}.
	 * @param WP_Post[] $posts    Posts.
	 * @return string
	 */
	public static function expand_post_loop( $item_tpl, $posts ) {
		$rows = '';
		foreach ( (array) $posts as $post ) {
			$img = self::image_url( $post, 'large' );
			$map = array(
				'{post_title}'   => esc_html( get_the_title( $post ) ),
				'{post_url}'     => esc_url( get_permalink( $post ) ),
				'{post_excerpt}' => esc_html( self::excerpt( $post ) ),
				'{post_image}'   => $img,
				'{post_date}'    => esc_html( get_the_date( '', $post ) ),
				'{post_author}'  => esc_html( get_the_author_meta( 'display_name', $post->post_author ) ),
			);
			$rows .= strtr( $item_tpl, $map );
		}
		return $rows;
	}

	/**
	 * A starter custom-HTML template the editor can pre-fill.
	 *
	 * @return string
	 */
	public static function custom_html_starter() {
		return implode(
			"\n",
			array(
				'<h1 style="font-size:22px;">{site_name}</h1>',
				'<p>{intro}</p>',
				'{posts}',
				'  <div style="margin:0 0 24px;">',
				'    <h2 style="font-size:18px;margin:0 0 6px;"><a href="{post_url}">{post_title}</a></h2>',
				'    <p style="margin:0 0 8px;color:#555;">{post_excerpt}</p>',
				'    <a href="{post_url}">Read more →</a>',
				'  </div>',
				'{/posts}',
			)
		);
	}
}
