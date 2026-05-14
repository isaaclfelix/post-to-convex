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
		add_action( 'init', array( $self, 'register_editor_script' ) );
		add_action( 'enqueue_block_editor_assets', array( $self, 'enqueue_editor_script' ) );
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

	/**
	 * Registers the block-editor-only script built to `build/editor.js`.
	 */
	public function register_editor_script() {
		$plugin_file = dirname( __DIR__ ) . '/post-to-convex.php';
		$asset_file  = __DIR__ . '/../build/editor.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_register_script(
			'post-to-convex-editor',
			plugins_url( 'build/editor.js', $plugin_file ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	/**
	 * Enqueues the block-editor-only script.
	 */
	public function enqueue_editor_script() {
		if ( ! wp_script_is( 'post-to-convex-editor', 'registered' ) ) {
			return;
		}

		wp_enqueue_script( 'post-to-convex-editor' );
	}
}