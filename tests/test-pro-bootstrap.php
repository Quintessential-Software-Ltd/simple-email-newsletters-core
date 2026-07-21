<?php
/**
 * Pro bootstrap integrity: every add-on class that semnewsp_boot() instantiates
 * or calls statically must be require_once'd in the same function, and every
 * file it requires must exist. Guards against the "Class SEMNEWSP_… not found"
 * fatal that a missing require_once produces at runtime (lint and the
 * dependency-free unit tests, which require the class files directly, do not
 * catch it).
 *
 * Mirror-safe: skips itself when the Pro add-on is absent (public core).
 *
 * @package SimpleEmailNewsletters\Tests
 */

// phpcs:ignoreFile

$semnews_boot_file = SEMNEWS_PLUGIN_DIR . 'addons/quintessential-newsletters-pro/quintessential-newsletters-pro.php';

if ( ! file_exists( $semnews_boot_file ) ) {
	return; // Public core mirror: no add-on to check.
}

$src = file_get_contents( $semnews_boot_file );

// Isolate the semnewsp_boot() function body so activation-hook requires (a
// separate scope) don't count as satisfying a boot-time instantiation.
$start = strpos( $src, 'function semnewsp_boot()' );
t( 'pro-bootstrap: semnewsp_boot() present', false !== $start );
$boot = substr( $src, $start );

// Files require_once'd inside semnewsp_boot().
preg_match_all( "/require_once SEMNEWSP_PLUGIN_DIR \. '(includes\/[a-z0-9-]+\.php)'/", $boot, $req_m );
$required_files   = array_unique( $req_m[1] );
$required_classes = array();
foreach ( $required_files as $rel ) {
	$path = SEMNEWS_PLUGIN_DIR . 'addons/quintessential-newsletters-pro/' . $rel;
	t( "pro-bootstrap: required file exists ($rel)", file_exists( $path ) );
	if ( file_exists( $path ) && preg_match( '/^class\s+([A-Za-z_][A-Za-z0-9_]+)/m', file_get_contents( $path ), $cm ) ) {
		$required_classes[ $cm[1] ] = true;
	}
}

// Add-on classes referenced in semnewsp_boot(): `new SEMNEWSP_X`, `new SEMNEWS_X`,
// `SEMNEWSP_X::`, and array( 'SEMNEWS_X', ... ) callables. Core classes (loaded by
// the free plugin) are exempt: keep an allow-list of the ones boot uses.
$core_classes = array( 'SEMNEWS_Plugin', 'SEMNEWS_VERSION' );

preg_match_all( '/\bnew\s+((?:SEMNEWSP|SEMNEWS)_[A-Za-z0-9_]+)\s*\(/', $boot, $new_m );
preg_match_all( '/\b((?:SEMNEWSP|SEMNEWS)_[A-Za-z0-9_]+)::/', $boot, $static_m );
preg_match_all( "/array\(\s*'((?:SEMNEWSP|SEMNEWS)_[A-Za-z0-9_]+)'/", $boot, $cb_m );

$referenced = array_unique( array_merge( $new_m[1], $static_m[1], $cb_m[1] ) );

// Classes the add-on itself defines (any SEMNEWSP_* or the SEMNEWS_* add-on classes).
$addon_defined = array();
foreach ( glob( SEMNEWS_PLUGIN_DIR . 'addons/quintessential-newsletters-pro/includes/*.php' ) as $f ) {
	if ( preg_match( '/^class\s+([A-Za-z_][A-Za-z0-9_]+)/m', file_get_contents( $f ), $m ) ) {
		$addon_defined[ $m[1] ] = true;
	}
}

foreach ( $referenced as $class ) {
	if ( in_array( $class, $core_classes, true ) ) {
		continue; // Provided by the free core.
	}
	if ( ! isset( $addon_defined[ $class ] ) ) {
		continue; // A core class (e.g. SEMNEWS_Mailer) used via a filter callback.
	}
	t( "pro-bootstrap: $class is require_once'd before use in semnewsp_boot()", isset( $required_classes[ $class ] ) );
}

// Specific guard for the exact regression that shipped: SEMNEWSP_Signup.
t( 'pro-bootstrap: SEMNEWSP_Signup is loaded in semnewsp_boot()', false !== strpos( $boot, "class-semnewsp-signup.php'" ) && isset( $required_classes['SEMNEWSP_Signup'] ) );
