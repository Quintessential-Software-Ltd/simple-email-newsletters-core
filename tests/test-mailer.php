<?php
/**
 * Mailer: preheader rendering in the shared layout and the plain-text
 * conversion (the preview text must survive into both MIME parts, and the
 * invisible padding must survive into neither).
 *
 * @package SimpleEmailNewsletters\Tests
 */

// phpcs:ignoreFile

require_once SEMNEWS_PLUGIN_DIR . 'includes/functions.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-mailer.php';

// The digest engine (and its preheader builder) ships in the Pro add-on;
// the related assertions below skip on the public core mirror.
$semnews_automation_file = SEMNEWS_PLUGIN_DIR . 'addons/quintessential-newsletters-pro/includes/class-semnews-automation.php';
if ( file_exists( $semnews_automation_file ) ) {
	require_once $semnews_automation_file;
}

semnews_test_reset();

// ---------------------------------------------------------------------------
// Preheader in the HTML part.
// ---------------------------------------------------------------------------

$html = SEMNEWS_Mailer::wrap( 'Test subject', '<p>Body content here.</p>', null, 'Sneak preview line' );

t( 'preheader: text present in wrapped HTML', false !== strpos( $html, 'Sneak preview line' ) );
t( 'preheader: hidden container rendered', false !== strpos( $html, 'class="semnews-preheader"' ) );
t( 'preheader: robust hiding styles applied', false !== strpos( $html, 'mso-hide:all' ) && false !== strpos( $html, 'font-size:1px' ) );
t( 'preheader: whitespace padding block present', false !== strpos( $html, 'class="semnews-preheader-pad"' ) );
t( 'preheader: padding sits after the text', strpos( $html, 'semnews-preheader-pad' ) > strpos( $html, 'Sneak preview line' ) );
t( 'preheader: rendered before the body content', strpos( $html, 'Sneak preview line' ) < strpos( $html, 'Body content here.' ) );
t( 'preheader: escaped', false !== strpos( SEMNEWS_Mailer::wrap( 'S', '<p>B</p>', null, 'a <b> & c' ), 'a &lt;b&gt; &amp; c' ) );

$no_pre = SEMNEWS_Mailer::wrap( 'Test subject', '<p>Body content here.</p>', null, '' );
t( 'preheader: block omitted entirely when empty', false === strpos( $no_pre, 'semnews-preheader' ) );

// ---------------------------------------------------------------------------
// Preheader in the plain-text part.
// ---------------------------------------------------------------------------

$plain = SEMNEWS_Mailer::to_plain_text( $html );

t( 'plain text: leads with the preheader', 0 === strpos( $plain, 'Sneak preview line' ) );
t( 'plain text: body content kept', false !== strpos( $plain, 'Body content here.' ) );
t( 'plain text: padding entities stripped', false === strpos( $plain, "\xC2\xA0\xE2\x80\x8C" ) );
t( 'plain text: <title> not leaked from <head>', false === strpos( $plain, 'Test subject' ) );

// ---------------------------------------------------------------------------
// Digest preheader derived from post titles.
// ---------------------------------------------------------------------------

$mk = function ( $title ) {
	$p = new stdClass();
	$p->post_title = $title;
	return $p;
};

if ( class_exists( 'SEMNEWS_Automation' ) ) {
	t( 'digest preheader: empty posts -> empty', '' === SEMNEWS_Automation::render_preheader( array() ) );
	t(
		'digest preheader: joins post titles',
		'First post · Second post' === SEMNEWS_Automation::render_preheader( array( $mk( 'First post' ), $mk( 'Second post' ) ) )
	);
	t( 'digest preheader: skips empty titles', 'Real' === SEMNEWS_Automation::render_preheader( array( $mk( '  ' ), $mk( 'Real' ) ) ) );

	$long = SEMNEWS_Automation::render_preheader( array(
		$mk( 'A quite long headline about a security incident that keeps going' ),
		$mk( 'Another long headline that certainly pushes the combined length far past the inbox preview budget' ),
	) );
	$long_len = function_exists( 'mb_strlen' ) ? mb_strlen( $long ) : strlen( $long );
	t( 'digest preheader: truncated to inbox length', $long_len <= 120 );
	t( 'digest preheader: truncation ends with ellipsis', '…' === substr( $long, -3 ) ); // '…' is 3 bytes in UTF-8.
	t( 'digest preheader: no mid-word cut', false === strpos( $long, 'budge' ) || false !== strpos( $long, 'budget' ) );
}

// ---------------------------------------------------------------------------
// Transactional header (branded top of the confirmation/welcome emails).
// ---------------------------------------------------------------------------

semnews_test_reset();

update_option( 'semnews_settings', array( 'company_name' => 'Acme Widgets' ) );
$hdr = SEMNEWS_Mailer::transactional_header();
t( 'header: wordmark built from the company name', false !== strpos( $hdr, 'Acme Widgets' ) && false === strpos( $hdr, '<img' ) );
t( 'header: wordmark links to the site', false !== strpos( $hdr, home_url( '/' ) ) );

$GLOBALS['semnews_test']['filters']['semnews_email_logo_url'] = 'https://example.com/logo.png';
$hdr = SEMNEWS_Mailer::transactional_header();
t( 'header: logo from the semnews_email_logo_url filter wins over the wordmark', false !== strpos( $hdr, '<img' ) && false !== strpos( $hdr, 'logo.png' ) );
unset( $GLOBALS['semnews_test']['filters']['semnews_email_logo_url'] );

update_option( 'semnews_settings', array( 'company_name' => '' ) );
t( 'header: empty when there is nothing to brand with', '' === SEMNEWS_Mailer::transactional_header() );

// ---------------------------------------------------------------------------
// Polished welcome + confirmation templates.
// ---------------------------------------------------------------------------

$sub         = new stdClass();
$sub->id     = 1;
$sub->name   = 'Pat';
$sub->email  = 'pat@example.com';
$sub->token  = 'tok';
$sub->status = 'subscribed';

$welcome_args = array(
	'subscriber'   => $sub,
	'company'      => 'Acme',
	'custom_intro' => '',
	'header'       => '<div id="hdr">H</div>',
	'site_url'     => 'https://example.test/',
	'site_name'    => 'Example',
);

$welcome = semnews_render_template( 'emails/welcome.php', $welcome_args );
t( 'welcome: opens with the branded header', 0 === strpos( trim( $welcome ), '<div id="hdr">' ) );
t( 'welcome: heading, greeting and CTA button rendered', false !== strpos( $welcome, '<h1' ) && false !== strpos( $welcome, 'Hi Pat,' ) && false !== strpos( $welcome, 'href="https://example.test/"' ) );

$welcome_args['custom_intro'] = "Line1\nLine2";
$welcome                      = semnews_render_template( 'emails/welcome.php', $welcome_args );
t( 'welcome: custom intro replaces the default paragraph', false !== strpos( $welcome, 'Line1<br />' ) && false === strpos( $welcome, 'great to have you' ) );

$conf = semnews_render_template(
	'emails/confirmation.php',
	array(
		'subscriber'   => $sub,
		'confirm_url'  => 'https://example.test/?semnews_action=confirm&t=tok',
		'company'      => 'Acme',
		'custom_intro' => '',
		'header'       => '',
		'site_url'     => 'https://example.test/',
	)
);
t( 'confirmation: button plus copy-paste fallback link', substr_count( $conf, 'semnews_action=confirm' ) >= 2 );
t( 'confirmation: trust note names the requesting site', false !== strpos( $conf, 'This request was made on' ) && false !== strpos( $conf, 'https://example.test/' ) );
