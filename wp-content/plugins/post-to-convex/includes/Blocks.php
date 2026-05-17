<?php
/**
 * Registers the plugin's blocks.
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
 * Registers the plugin's blocks.
 */
class Blocks {

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		$self = new self();
		add_action( 'init', array( $self, 'register_blocks' ) );
		add_action( 'init', array( $self, 'register_editor_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $self, 'enqueue_editor_assets' ) );
	}

	/**
	 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
	 * based on the registered block metadata. Behind the scenes, it registers also all assets so they can be enqueued
	 * through the block editor in the corresponding context.
	 *
	 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
	 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		wp_register_block_types_from_metadata_collection( __DIR__ . '/../build', __DIR__ . '/../build/blocks-manifest.php' );
	}

	/**
	 * Registers the block-editor-only script built to `build/editor.js`.
	 *
	 * @return void
	 */
	public function register_editor_assets(): void {
		$asset_file = __DIR__ . '/../build/editor.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$plugin_file = dirname( __DIR__ ) . '/post-to-convex.php';

		$asset = include $asset_file;

		wp_register_style(
			'post-to-convex-editor',
			plugins_url( 'build/editor.css', $plugin_file ),
			array(),
			$asset['version']
		);

		wp_register_script(
			'post-to-convex-editor',
			plugins_url( 'build/editor.js', $plugin_file ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'post-to-convex-editor',
			'postToConvexEditor',
			array(
				'remoteIdMetaKey' => PostMeta::REMOTE_ID_META_KEY,
				'scriptDebug'     => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			)
		);
	}

	/**
	 * Enqueues the block-editor-only assets.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		if ( wp_style_is( 'post-to-convex-editor', 'registered' ) ) {
			wp_enqueue_style( 'post-to-convex-editor' );
		}

		if ( wp_script_is( 'post-to-convex-editor', 'registered' ) ) {
			wp_enqueue_script( 'post-to-convex-editor' );
		}
	}
}
