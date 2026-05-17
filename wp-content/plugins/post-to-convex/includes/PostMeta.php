<?php
/**
 * Registers post meta exposed to the REST API and the block editor.
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
 * Post meta keys for Post to Convex.
 */
class PostMeta {

	/**
	 * Meta key for the Convex (or remote) document id after a post is synced.
	 *
	 * @var string
	 */
	public const REMOTE_ID_META_KEY = 'post_to_convex_remote_id';

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		$self = new self();
		add_action( 'init', array( $self, 'register_meta' ) );
	}

	/**
	 * Register meta for all post types that participate in the REST block editor.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		$post_types = get_post_types(
			array(
				'show_in_rest' => true,
			),
			'names'
		);

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				self::REMOTE_ID_META_KEY,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => array( $this, 'auth_remote_id_meta' ),
				)
			);
		}
	}

	/**
	 * Limit meta visibility and edits to users who can edit the post.
	 *
	 * @param bool   $allowed   Whether the user can add this meta.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Post ID.
	 * @return bool
	 */
	public function auth_remote_id_meta( bool $allowed, string $meta_key, int $object_id ): bool {
		return (bool) current_user_can( 'edit_post', $object_id );
	}
}
