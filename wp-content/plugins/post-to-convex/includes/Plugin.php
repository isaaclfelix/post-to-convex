<?php
/**
 * Plugin bootstrap.
 *
 * @package PostToConvex
 */

declare( strict_types=1 );

namespace PostToConvex;

/**
 * Security check.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Loads plugin services on `plugins_loaded`.
 */
class Plugin {

	/**
	 * Boot plugin services after WordPress loads.
	 *
	 * @return void
	 */
	public static function boot(): void {
		Blocks::init();
		PostMeta::init();
		RestApi::init();

		if ( ! is_admin() ) {
			return;
		}

		AdminSettings::init();
	}
}
