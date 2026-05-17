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

declare( strict_types=1 );

/**
 * Security check.
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

use PostToConvex\Plugin;

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
