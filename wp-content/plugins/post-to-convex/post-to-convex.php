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

require_once __DIR__ . '/includes/class-secret-store.php';
require_once __DIR__ . '/includes/class-admin-settings.php';
require_once __DIR__ . '/includes/class-blocks.php';

add_action(
	'plugins_loaded',
	static function () {
		Post_To_Convex_Blocks::init();

		if ( ! is_admin() ) {
			return;
		}

		Post_To_Convex_Admin_Settings::init();
	}
);
