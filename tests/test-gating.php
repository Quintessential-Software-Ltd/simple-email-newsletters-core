<?php
/**
 * Free-tier template gating: the free registry ships Simple only, and any
 * unregistered template id (the Pro gallery, Custom HTML) degrades to the
 * Simple layout instead of breaking. Pure core — this file also runs in the
 * public core mirror, where the Pro add-on files do not exist.
 *
 * @package SimpleEmailNewsletters\Tests
 */

// phpcs:ignoreFile

semnews_test_reset();

$free = SEMNEWS_Templates::get_templates();
t( 'gating: free registry is Simple only', array( 'simple' ) === array_keys( $free ) );
t( 'gating: gallery + custom ids unknown to free', ! SEMNEWS_Templates::exists( 'cards' ) && ! SEMNEWS_Templates::exists( 'magazine' ) && ! SEMNEWS_Templates::exists( 'text' ) && ! SEMNEWS_Templates::exists( 'custom' ) );

$mk_gating_post = function ( $id, $title ) {
	$p               = new stdClass();
	$p->ID           = $id;
	$p->post_title   = $title;
	$p->post_excerpt = 'Excerpt.';
	$p->post_content = 'Excerpt.';
	$p->post_author  = 7;
	return $p;
};
$gating_posts = array( $mk_gating_post( 21, 'Alpha post' ), $mk_gating_post( 22, 'Beta post' ) );

// A stored gallery id renders as Simple in free (graceful degrade, not a blank).
$fallback = SEMNEWS_Templates::render( 'cards', $gating_posts, array() );
$simple   = SEMNEWS_Templates::render( 'simple', $gating_posts, array() );
t( 'gating: unregistered template renders the Simple layout', $fallback === $simple && false !== strpos( $fallback, 'Alpha post' ) );

// Custom HTML is Pro-registered too: in free the id falls back to Simple even
// when custom markup is supplied.
$gated = SEMNEWS_Templates::render( 'custom', $gating_posts, array( 'custom_html' => '<b>{post_title}</b>' ) );
t( 'gating: custom template gated without Pro', $gated === $simple && false === strpos( $gated, '<b>' ) );
