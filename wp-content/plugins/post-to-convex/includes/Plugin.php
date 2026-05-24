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
	 * Plugin version.
	 *
	 * @var string
	 */
	public const VERSION = '0.1.0';

	/**
	 * Boot plugin services after WordPress loads.
	 *
	 * @return void
	 */
	public static function boot(): void {
		Blocks::init();
		PostMeta::init();
		AttachmentMeta::init();
		TermMeta::init();
		MediaSync::init();
		RestApi::init();

		if ( ! is_admin() ) {
			return;
		}

		AdminSettings::init();
		TaxonomyFields::init();
	}
}
