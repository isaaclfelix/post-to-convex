<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Post_To_Convex
 */

declare( strict_types=1 );

$post_to_convex_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $post_to_convex_tests_dir ) {
	$post_to_convex_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$post_to_convex_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $post_to_convex_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $post_to_convex_phpunit_polyfills_path ); // phpcs:ignore
}

if ( ! file_exists( "{$post_to_convex_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$post_to_convex_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$post_to_convex_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 *
 * @return void
 */
function post_to_convex_manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/post-to-convex.php';
}

tests_add_filter( 'muplugins_loaded', 'post_to_convex_manually_load_plugin' );

// Start up the WP testing environment.
require "{$post_to_convex_tests_dir}/includes/bootstrap.php";
