<?php
/**
 * Plugin Name:     Post to Convex
 * Plugin URI:      http://beddev.lobodeguerra.com
 * Description:     A plugin to send data to a Convex powered backend
 * Author:          Isaac L. Félix
 * Author URI:      http://beddev.lobodeguerra.com
 * Text Domain:     post-to-convex
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Post_To_Convex
 */

defined( 'ABSPATH' ) || exit;

define( 'POST_TO_CONVEX_VERSION', '0.1.0' );

require_once __DIR__ . '/includes/class-post-to-convex-secret-store.php';
require_once __DIR__ . '/includes/class-post-to-convex-admin-settings.php';
require_once __DIR__ . '/includes/class-post-to-convex-blocks.php';
require_once __DIR__ . '/includes/class-post-to-convex-post-meta.php';
require_once __DIR__ . '/includes/class-post-to-convex-rest-api.php';

add_action(
	'plugins_loaded',
	static function () {
		Post_To_Convex_Blocks::init();
		Post_To_Convex_Post_Meta::init();
		Post_To_Convex_Rest_Api::init();

		if ( ! is_admin() ) {
			return;
		}

		Post_To_Convex_Admin_Settings::init();
	}
);
