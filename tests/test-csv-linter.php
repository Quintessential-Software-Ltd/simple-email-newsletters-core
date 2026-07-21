<?php
/**
 * CSV formula-injection guard + honesty linter.
 *
 * @package QuintessentialNewsletters\Tests
 */

// phpcs:ignoreFile

require_once SEMNEWS_PLUGIN_DIR . 'includes/functions.php';
require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-linter.php';

semnews_test_reset();

// --- semnews_csv_field (CWE-1236) ---
t( 'csv: = neutralized', semnews_csv_field( '=HYPERLINK("x")' ) === "'=HYPERLINK(\"x\")" );
t( 'csv: + neutralized', semnews_csv_field( '+1' ) === "'+1" );
t( 'csv: - neutralized', semnews_csv_field( '-2' ) === "'-2" );
t( 'csv: @ neutralized', semnews_csv_field( '@cmd' ) === "'@cmd" );
t( 'csv: tab neutralized', semnews_csv_field( "\tx" ) === "'\tx" );
t( 'csv: CR neutralized', semnews_csv_field( "\rx" ) === "'\rx" );
t( 'csv: normal name untouched', semnews_csv_field( 'Jane Doe' ) === 'Jane Doe' );
t( 'csv: email untouched', semnews_csv_field( 'a@b.com' ) === 'a@b.com' );
t( 'csv: empty untouched', semnews_csv_field( '' ) === '' );

// --- SEMNEWS_Linter ---
$codes = function ( $issues ) {
	return array_map( function ( $i ) { return $i['code']; }, $issues );
};
$has = function ( $issues, $code ) use ( $codes ) {
	return in_array( $code, $codes( $issues ), true );
};

t( 'lint: fake Re: flagged', $has( SEMNEWS_Linter::lint_subject( 'Re: your account' ), 'fake_reply' ) );
t( 'lint: fwd flagged', $has( SEMNEWS_Linter::lint_subject( 'Fwd: news' ), 'fake_reply' ) );
t( 'lint: ALL CAPS flagged', $has( SEMNEWS_Linter::lint_subject( 'BUY NOW CHEAP DEALS' ), 'all_caps' ) );
t( 'lint: normal subject clean', ! $has( SEMNEWS_Linter::lint_subject( 'Our June newsletter' ), 'all_caps' ) && ! $has( SEMNEWS_Linter::lint_subject( 'Our June newsletter' ), 'fake_reply' ) );
t( 'lint: multi exclamation flagged', $has( SEMNEWS_Linter::lint_subject( 'Sale!!!' ), 'punctuation' ) );
t( 'lint: empty subject flagged', $has( SEMNEWS_Linter::lint_subject( '' ), 'empty_subject' ) );
t( 'lint: long subject info', $has( SEMNEWS_Linter::lint_subject( str_repeat( 'word ', 20 ) ), 'too_long' ) );

$c1 = SEMNEWS_Linter::lint_campaign( 'Hello', '<img src="x.jpg">', '' );
t( 'lint: image-only flagged', $has( $c1, 'image_only' ) );
t( 'lint: missing preheader flagged', $has( $c1, 'no_preheader' ) );
$c2 = SEMNEWS_Linter::lint_campaign( 'Hello there', '<p>Plenty of real readable text here for the digest body.</p>', 'A preview line' );
t( 'lint: good campaign no image_only', ! $has( $c2, 'image_only' ) );
t( 'lint: good campaign no preheader warn', ! $has( $c2, 'no_preheader' ) );
t( 'lint: render escapes and outputs', strlen( SEMNEWS_Linter::render_notice_html( $c1 ) ) > 0 );
