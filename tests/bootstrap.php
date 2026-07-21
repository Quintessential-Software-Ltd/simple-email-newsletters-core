<?php
/**
 * Dependency-free test bootstrap: minimal WordPress stubs so the plugin's pure
 * logic (linter, scheduling, webhook parsing, sender profiles, CSV escaping,
 * license verification) can be exercised with plain `php tests/run-tests.php`
 * — no WordPress install or PHPUnit required.
 *
 * @package QuintessentialNewsletters\Tests
 */

// phpcs:ignoreFile

error_reporting( E_ALL );

define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'YEAR_IN_SECONDS', 31536000 );
define( 'SEMNEWS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'SEMNEWS_PLUGIN_URL', 'https://example.com/wp-content/plugins/quintessential-newsletters/' );
define( 'SEMNEWS_PLUGIN_BASENAME', 'quintessential-newsletters/quintessential-newsletters.php' );
define( 'SEMNEWS_VERSION', 'test' );
define( 'SEMNEWS_FREE_SUBSCRIBER_LIMIT', 50 );

$GLOBALS['semnews_test'] = array(
	'options'    => array(),
	'transients' => array(),
	'filters'    => array(), // name => forced return value.
	'actions'    => array(), // recorded do_action calls.
	'http'       => array(), // queued wp_remote_* responses.
	'http_gets'  => array(),
	'home_url'   => 'https://example.com/',
	'timezone'   => 'UTC',
);

function semnews_test_reset() {
	$GLOBALS['semnews_test']['options']    = array();
	$GLOBALS['semnews_test']['transients'] = array();
	$GLOBALS['semnews_test']['filters']    = array();
	$GLOBALS['semnews_test']['actions']    = array();
	$GLOBALS['semnews_test']['http']       = array();
	$GLOBALS['semnews_test']['http_gets']  = array();
	$GLOBALS['semnews_test']['home_url']   = 'https://example.com/';
	$GLOBALS['semnews_test']['timezone']   = 'UTC';
}

// ---------------------------------------------------------------------------
// Assertion helpers.
// ---------------------------------------------------------------------------

$GLOBALS['semnews_test_pass'] = 0;
$GLOBALS['semnews_test_fail'] = 0;

function t( $label, $condition ) {
	if ( $condition ) {
		$GLOBALS['semnews_test_pass']++;
		echo "PASS :: {$label}\n";
	} else {
		$GLOBALS['semnews_test_fail']++;
		echo "FAIL :: {$label}\n";
	}
}

// ---------------------------------------------------------------------------
// WordPress stubs.
// ---------------------------------------------------------------------------

function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['semnews_test']['options'] ) ? $GLOBALS['semnews_test']['options'][ $k ] : $d;
}
function update_option( $k, $v, $autoload = null ) {
	$GLOBALS['semnews_test']['options'][ $k ] = $v;
	return true;
}
function add_option( $k, $v, $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $k, $GLOBALS['semnews_test']['options'] ) ) {
		return false;
	}
	$GLOBALS['semnews_test']['options'][ $k ] = $v;
	return true;
}
function delete_option( $k ) {
	unset( $GLOBALS['semnews_test']['options'][ $k ] );
	return true;
}
function get_transient( $k ) {
	return array_key_exists( $k, $GLOBALS['semnews_test']['transients'] ) ? $GLOBALS['semnews_test']['transients'][ $k ] : false;
}
function set_transient( $k, $v, $ttl = 0 ) {
	$GLOBALS['semnews_test']['transients'][ $k ] = $v;
	return true;
}
function delete_transient( $k ) {
	unset( $GLOBALS['semnews_test']['transients'][ $k ] );
	return true;
}

function apply_filters( $name, $value ) {
	if ( array_key_exists( $name, $GLOBALS['semnews_test']['filters'] ) ) {
		return $GLOBALS['semnews_test']['filters'][ $name ];
	}
	return $value;
}
function do_action( $name, ...$args ) {
	$GLOBALS['semnews_test']['actions'][] = array( 'name' => $name, 'args' => $args );
}
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function add_shortcode( ...$a ) {}
function register_rest_route( ...$a ) {}
function shortcode_atts( $defaults, $atts, $shortcode = '' ) {
	return array_merge( $defaults, array_intersect_key( (array) $atts, $defaults ) );
}

function __( $s, $d = null ) { return $s; }
function _n( $single, $plural, $n, $d = null ) { return 1 === (int) $n ? $single : $plural; }
function esc_html__( $s, $d = null ) { return $s; }
function esc_html_e( $s, $d = null ) { echo htmlspecialchars( (string) $s, ENT_QUOTES ); }
function _e( $s, $d = null ) { echo $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr__( $s, $d = null ) { return $s; }
function esc_url( $u ) { return (string) $u; }
function esc_url_raw( $u ) { return (string) $u; }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_js( $s ) { return (string) $s; }
function wp_kses_post( $s ) { return (string) $s; }
function wp_kses( $s, $allowed_html = array(), $allowed_protocols = array() ) { return (string) $s; }
function wp_kses_allowed_html( $context = '' ) { return array(); }

function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
function sanitize_email( $s ) {
	$s = trim( (string) $s );
	return preg_match( '/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $s ) ? $s : '';
}
function is_email( $s ) { return (bool) sanitize_email( $s ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_title( $s ) {
	$s = strtolower( trim( (string) $s ) );
	$s = preg_replace( '/[\s]+/', '-', $s );
	return preg_replace( '/[^a-z0-9\-_]/', '', $s );
}
function absint( $v ) { return abs( (int) $v ); }
function wp_unslash( $v ) { return $v; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, is_array( $args ) ? $args : array() ); }
function wp_rand( $min = 0, $max = 0 ) { return random_int( $min ?: 0, $max ?: PHP_INT_MAX ); }
function wp_generate_password( $length = 12, $special = true, $extra = false ) {
	return substr( bin2hex( random_bytes( 64 ) ), 0, $length );
}
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function wp_strip_all_tags( $s ) { return trim( preg_replace( '/<[^>]*>/', ' ', (string) $s ) ); }
function wp_trim_words( $text, $num = 55, $more = '…' ) {
	$words = preg_split( '/\s+/', trim( (string) $text ) );
	return count( $words ) > $num ? implode( ' ', array_slice( $words, 0, $num ) ) . $more : $text;
}
function number_format_i18n( $n ) { return number_format( (float) $n ); }

function wp_timezone() { return new DateTimeZone( $GLOBALS['semnews_test']['timezone'] ); }
function wp_timezone_string() { return $GLOBALS['semnews_test']['timezone']; }
function current_time( $type, $gmt = 0 ) {
	if ( 'mysql' === $type ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
	return gmdate( $type );
}
function home_url( $path = '' ) { return $GLOBALS['semnews_test']['home_url']; }
function get_bloginfo( $key = '' ) { return 'Test Site'; }
function post_type_exists( $t ) { return 'post' === $t; }
function is_rtl() { return false; }
function get_stylesheet_directory() { return '/nonexistent-a'; }
function get_template_directory() { return '/nonexistent-b'; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }

class WP_Error {
	public $message;
	public function __construct( $code = '', $message = '' ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function wp_remote_post( $url, $args = array() ) {
	$next = array_shift( $GLOBALS['semnews_test']['http'] );
	return null === $next ? new WP_Error( 'no_mock', 'no queued response' ) : $next;
}
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['semnews_test']['http_gets'][] = $url;
	$next = array_shift( $GLOBALS['semnews_test']['http'] );
	return null === $next ? array( 'body' => '', 'code' => 200 ) : $next;
}
function wp_remote_retrieve_body( $r ) { return is_array( $r ) && isset( $r['body'] ) ? $r['body'] : ''; }
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) && isset( $r['code'] ) ? $r['code'] : 0; }

/** Queue a mocked HTTP response: semnews_test_http(array('key' => ...)) */
function semnews_test_http( $decoded, $code = 200 ) {
	$GLOBALS['semnews_test']['http'][] = array( 'body' => json_encode( $decoded ), 'code' => $code );
}
