<?php
/**
 * Automated digest — catch-up slot semantics, newsletter-based rendering and
 * the 2.2.0 legacy-config migration. The engine ships in the Pro add-on
 * (automated sending is a Pro feature), so this file is mirror-safe: it
 * skips itself when the add-on is absent (public core).
 *
 * @package SimpleEmailNewsletters\Tests
 */

// phpcs:ignoreFile

// -- WP post-function stubs so the real SEMNEWS_Templates renderers run. ---------

function get_the_title( $post ) { return $post->post_title; }
function get_permalink( $post ) { return 'https://example.com/?p=' . (int) $post->ID; }
function get_the_date( $format, $post ) { return '1 July 2026'; }
function get_the_author_meta( $field, $author_id ) { return 'Author ' . (int) $author_id; }
function get_the_post_thumbnail_url( $post, $size = 'large' ) { return ! empty( $post->thumbnail ) ? $post->thumbnail : false; }
function has_excerpt( $post ) { return ! empty( $post->post_excerpt ); }
function get_the_excerpt( $post ) { return $post->post_excerpt; }
function strip_shortcodes( $text ) { return $text; }
function date_i18n( $format, $timestamp = null ) { return '4 July 2026'; }

// -- In-memory SEMNEWS_Campaigns stand-in (real one is a $wpdb wrapper). ---------

if ( ! class_exists( 'SEMNEWS_Campaigns' ) ) {
	class SEMNEWS_Campaigns {
		const STATUS_DRAFT     = 'draft';
		const STATUS_SCHEDULED = 'scheduled';
		const STATUS_SENDING   = 'sending';
		const STATUS_SENT      = 'sent';
		const STATUS_PAUSED    = 'paused';

		public static $rows    = array();
		public static $next_id = 1;

		public static function get( $id ) {
			return isset( self::$rows[ (int) $id ] ) ? self::$rows[ (int) $id ] : null;
		}

		public static function create( $data ) {
			$row           = (object) $data;
			$row->id       = self::$next_id++;
			$row->subject  = isset( $data['subject'] ) ? $data['subject'] : '';
			$row->body     = isset( $data['body'] ) ? $data['body'] : '';
			$row->template = isset( $data['template'] ) ? $data['template'] : null;

			self::$rows[ $row->id ] = $row;
			return $row->id;
		}

		public static function sanitize_body( $body ) {
			return $body; // Passthrough: kses behaviour is not under test here.
		}
	}
}

// Core templates class first: later test files (test-gating.php) rely on it
// being loaded here, whether or not the add-on is present.
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-templates.php';

$semnews_automation_file = SEMNEWS_PLUGIN_DIR . 'addons/quintessential-newsletters-pro/includes/class-semnews-automation.php';
if ( ! file_exists( $semnews_automation_file ) ) {
	return; // Public core mirror: no add-on, no digest engine to test.
}

require_once $semnews_automation_file;

semnews_test_reset();
$GLOBALS['semnews_test']['timezone'] = 'Europe/London';

$cfg = function ( $freq, $hour, $wd = 1, $md = 1, $last = 0 ) {
	return array(
		'frequency'     => $freq,
		'send_hour'     => $hour,
		'send_weekday'  => $wd,
		'send_monthday' => $md,
		'last_run'      => $last,
	);
};
$ts = function ( $str ) {
	return ( new DateTimeImmutable( $str, new DateTimeZone( 'Europe/London' ) ) )->getTimestamp();
};

// 2026-07-01 is a Wednesday; 2026-06-29 is a Monday.
$now = $ts( '2026-07-01 14:00' );

t( 'digest: daily missed 09h tick still due at 14h (catch-up)', SEMNEWS_Automation::is_due( $cfg( 'daily', 9, 1, 1, $ts( '2026-06-30 09:05' ) ), $now ) === true );
t( 'digest: daily already ran after today slot -> not due', SEMNEWS_Automation::is_due( $cfg( 'daily', 9, 1, 1, $ts( '2026-07-01 09:05' ) ), $now ) === false );
t( 'digest: daily before hour -> not due', SEMNEWS_Automation::is_due( $cfg( 'daily', 9, 1, 1, $ts( '2026-06-30 09:05' ) ), $ts( '2026-07-01 08:00' ) ) === false );

t( 'digest: weekly due right after Monday slot', SEMNEWS_Automation::is_due( $cfg( 'weekly', 9, 1, 1, $ts( '2026-06-22 09:05' ) ), $ts( '2026-06-29 09:05' ) ) === true );
t( 'digest: weekly Wednesday catch-up when Monday missed', SEMNEWS_Automation::is_due( $cfg( 'weekly', 9, 1, 1, $ts( '2026-06-22 09:05' ) ), $ts( '2026-07-01 16:00' ) ) === true );
t( 'digest: weekly ran Monday -> Wednesday not due', SEMNEWS_Automation::is_due( $cfg( 'weekly', 9, 1, 1, $ts( '2026-06-29 09:05' ) ), $ts( '2026-07-01 16:00' ) ) === false );

t( 'digest: monthly July 3rd catch-up for July 1 slot', SEMNEWS_Automation::is_due( $cfg( 'monthly', 9, 1, 1, $ts( '2026-06-01 09:30' ) ), $ts( '2026-07-03 12:00' ) ) === true );
t( 'digest: monthly ran on the 1st -> not due on the 3rd', SEMNEWS_Automation::is_due( $cfg( 'monthly', 9, 1, 1, $ts( '2026-07-01 09:30' ) ), $ts( '2026-07-03 12:00' ) ) === false );
t( 'digest: monthly June 30 -> July slot not yet due', SEMNEWS_Automation::is_due( $cfg( 'monthly', 9, 1, 1, $ts( '2026-06-01 09:30' ) ), $ts( '2026-06-30 08:00' ) ) === false );

t( 'digest: baseline last_run=now blocks todays past slot', SEMNEWS_Automation::is_due( $cfg( 'daily', 9, 1, 1, $now ), $now + 60 ) === false );
t( 'digest: fires at next slot after enabling', SEMNEWS_Automation::is_due( $cfg( 'daily', 9, 1, 1, $now ), $ts( '2026-07-02 09:00' ) + 30 ) === true );
t( 'digest: slot sane across DST boundary', SEMNEWS_Automation::last_due_slot( $cfg( 'daily', 9 ), $ts( '2026-03-29 12:00' ) ) > 0 );

// -- Newsletter-based digest rendering. ---------------------------------------

$make_post = function ( $id, $title, $excerpt = 'Excerpt.' ) {
	$p               = new stdClass();
	$p->ID           = $id;
	$p->post_title   = $title;
	$p->post_excerpt = $excerpt;
	$p->post_content = $excerpt;
	$p->post_author  = 7;
	return $p;
};
$posts = array( $make_post( 11, 'First post' ), $make_post( 12, 'Second post' ) );

$newsletter = function ( $body, $template = null, $subject = 'Weekly round-up', $preheader = '' ) {
	$c            = new stdClass();
	$c->id        = 999;
	$c->subject   = $subject;
	$c->preheader = $preheader;
	$c->body      = $body;
	$c->template  = $template;
	return $c;
};

// A {posts} loop with per-post tags repeats for each post.
$body = SEMNEWS_Automation::render_newsletter_body( $newsletter( '<p>Hello.</p>{posts}<h2>{post_title}</h2>{/posts}<p>Bye.</p>' ), $posts );
t( 'render: {posts} loop expands per post', false !== strpos( $body, '<h2>First post</h2><h2>Second post</h2>' ) );
t( 'render: static content around the loop kept', false !== strpos( $body, '<p>Hello.</p>' ) && false !== strpos( $body, '<p>Bye.</p>' ) );
t( 'render: no tags leak into the output', false === strpos( $body, '{posts}' ) && false === strpos( $body, '{post_title}' ) );

// An empty block inserts the standard layout for the newsletter's template.
$body = SEMNEWS_Automation::render_newsletter_body( $newsletter( '<p>Intro text.</p>{posts}{/posts}', 'simple' ), $posts );
t( 'render: empty block uses the standard template layout', false !== strpos( $body, 'First post' ) && false !== strpos( $body, 'Read more' ) );

// Editor-wrapped tags (<p>{posts}</p>…<p>{/posts}</p>) still count as empty.
$body = SEMNEWS_Automation::render_newsletter_body( $newsletter( "<p>{posts}</p>\n<p>{/posts}</p>", 'text' ), $posts );
t( 'render: markup-only block treated as empty', false !== strpos( $body, 'First post' ) && false === strpos( $body, '{/posts}' ) );

// No block at all: posts are appended so a digest always carries new content.
$body = SEMNEWS_Automation::render_newsletter_body( $newsletter( '<p>Just my words.</p>' ), $posts );
t( 'render: posts appended when no block present', false !== strpos( $body, '<p>Just my words.</p>' ) && false !== strpos( $body, 'Second post' ) );

// Global tags resolve in body and subject; legacy {intro} disappears.
$body = SEMNEWS_Automation::render_newsletter_body( $newsletter( '<h1>{site_name}</h1><p>{date}</p><p>x{intro}y</p>{posts}{/posts}' ), $posts );
t( 'render: {date} resolved and {intro} removed', false !== strpos( $body, '4 July 2026' ) && false !== strpos( $body, '<p>xy</p>' ) );
t( 'render: subject tags resolved', '4 July 2026' === SEMNEWS_Automation::render_newsletter_subject( $newsletter( '', null, '{date}' ) ) );

// Preheader: the newsletter's own wins; otherwise honest post titles.
t( 'render: newsletter preheader wins', 'Fresh reads' === SEMNEWS_Automation::render_newsletter_preheader( $newsletter( '', null, 'S', 'Fresh reads' ), $posts ) );
t( 'render: fallback preheader lists post titles', 'First post · Second post' === SEMNEWS_Automation::render_newsletter_preheader( $newsletter( '' ), $posts ) );

// -- Config: campaign_id validation. ------------------------------------------

semnews_test_reset();
$GLOBALS['semnews_test']['timezone'] = 'Europe/London';
SEMNEWS_Campaigns::$rows    = array();
SEMNEWS_Campaigns::$next_id = 1;

$existing = SEMNEWS_Campaigns::create( array( 'subject' => 'Digest source', 'body' => '{posts}{/posts}' ) );

$saved = SEMNEWS_Automation::save_config( array( 'campaign_id' => $existing ) );
t( 'config: existing newsletter id accepted', $saved['campaign_id'] === $existing );

$saved = SEMNEWS_Automation::save_config( array( 'campaign_id' => 12345 ) );
t( 'config: unknown newsletter id rejected to 0', 0 === $saved['campaign_id'] );

// -- 2.2.0 migration: legacy authored config -> real draft newsletter. --------

semnews_test_reset();
SEMNEWS_Campaigns::$rows    = array();
SEMNEWS_Campaigns::$next_id = 1;

// No stored config at all: nothing to do, nothing created.
SEMNEWS_Automation::maybe_migrate_legacy_config();
t( 'migration: absent config untouched', false === get_option( 'semnews_automation' ) && empty( SEMNEWS_Campaigns::$rows ) );

// Pristine disabled config: marked migrated, but no newsletter is invented.
update_option( 'semnews_automation', array( 'enabled' => 0 ) );
SEMNEWS_Automation::maybe_migrate_legacy_config();
$after = get_option( 'semnews_automation' );
t( 'migration: pristine config gets campaign_id=0, no draft created', 0 === $after['campaign_id'] && empty( SEMNEWS_Campaigns::$rows ) );

// In-use built-in-template config becomes a draft with an empty {posts} block.
update_option(
	'semnews_automation',
	array(
		'enabled'  => 1,
		'template' => 'cards',
		'subject'  => 'Monthly news',
		'intro'    => 'Hand-picked for you.',
		'last_run' => 123,
	)
);
SEMNEWS_Automation::maybe_migrate_legacy_config();
$after = get_option( 'semnews_automation' );
$draft = SEMNEWS_Campaigns::get( $after['campaign_id'] );
t( 'migration: in-use config points at a new draft', $after['campaign_id'] > 0 && null !== $draft );
// 'cards' moved to the Pro gallery; without Pro the migration falls back to
// the registered default so the digest keeps rendering.
t( 'migration: draft keeps subject, unregistered template falls back', 'Monthly news' === $draft->subject && 'simple' === $draft->template );
t( 'migration: intro baked in above an empty {posts} block', "<p>Hand-picked for you.</p>\n{posts}{/posts}" === $draft->body );
t( 'migration: schedule state preserved', 123 === $after['last_run'] && 1 === $after['enabled'] );

// Running it again must not create a second draft.
$count = count( SEMNEWS_Campaigns::$rows );
SEMNEWS_Automation::maybe_migrate_legacy_config();
t( 'migration: idempotent', count( SEMNEWS_Campaigns::$rows ) === $count );

// Custom-HTML config keeps its exact markup, with {intro} baked in as text.
delete_option( 'semnews_automation' );
update_option(
	'semnews_automation',
	array(
		'enabled'     => 1,
		'template'    => 'custom',
		'custom_html' => '<div>{intro}</div>{posts}<b>{post_title}</b>{/posts}',
		'intro'       => 'Hi there',
	)
);
SEMNEWS_Automation::maybe_migrate_legacy_config();
$after = get_option( 'semnews_automation' );
$draft = SEMNEWS_Campaigns::get( $after['campaign_id'] );
t( 'migration: custom HTML carried over with intro literal', '<div>Hi there</div>{posts}<b>{post_title}</b>{/posts}' === $draft->body );

// -- run(): a missing newsletter blocks the send without burning the slot. ----

update_option( 'semnews_automation', array( 'enabled' => 1, 'campaign_id' => 0, 'last_run' => 55 ) );
$automation = new SEMNEWS_Automation();
$result     = $automation->run( true );
$after      = get_option( 'semnews_automation' );
t( 'run: no newsletter -> blocked with code no_newsletter', false === $result['sent'] && 'no_newsletter' === $result['code'] );
t( 'run: no newsletter -> last_run not advanced', 55 === $after['last_run'] && 'no_newsletter' === $after['last_status'] );
