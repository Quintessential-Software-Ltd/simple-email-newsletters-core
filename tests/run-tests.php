<?php
/**
 * Test runner: php tests/run-tests.php
 *
 * Exits non-zero on any failure so CI can gate on it.
 *
 * @package SimpleEmailNewsletters\Tests
 */

// phpcs:ignoreFile

require __DIR__ . '/bootstrap.php';

foreach ( glob( __DIR__ . '/test-*.php' ) as $file ) {
	echo "\n== " . basename( $file ) . " ==\n";
	require $file;
}

echo "\n----------------------------------------\n";
printf( "Passed: %d   Failed: %d\n", $GLOBALS['semnews_test_pass'], $GLOBALS['semnews_test_fail'] );

exit( $GLOBALS['semnews_test_fail'] > 0 ? 1 : 0 );
