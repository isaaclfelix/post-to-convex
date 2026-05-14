<?php
/**
 * Registers the plugin's blocks.
 *
 * @package Post_To_Convex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's blocks.
 */
class Post_To_Convex_Blocks {

	/**
	 * Boot hooks.
	 */
	public static function init() {
		$self = new self();
		add_action( 'init', array( $self, 'register_blocks' ) );
	}

	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
	 * based on the registered block metadata. Behind the scenes, it registers also all assets so they can be enqueued
	 * through the block editor in the corresponding context.
	 *
	 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
	 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
	 */
	public function register_blocks() {
		wp_register_block_types_from_metadata_collection( __DIR__ . '/../build', __DIR__ . '/../build/blocks-manifest.php' );
	}
}