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
 * @package PostToConvex
 */

defined( 'ABSPATH' ) || exit;

define( 'POST_TO_CONVEX_VERSION', '0.1.0' );

require_once __DIR__ . '/vendor/autoload.php';

use PostToConvex\AdminSettings;
use PostToConvex\Blocks;
use PostToConvex\PostMeta;
use PostToConvex\RestApi;

add_action(
	'plugins_loaded',
	static function () {
		Blocks::init();
		PostMeta::init();
		RestApi::init();

		if ( ! is_admin() ) {
			return;
		}

		AdminSettings::init();
	}
);
