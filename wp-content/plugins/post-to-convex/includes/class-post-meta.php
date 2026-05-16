<?php
/**
 * Registers post meta exposed to the REST API and the block editor.
 *
 * @package Post_To_Convex
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post meta keys for Post to Convex.
 */
class Post_To_Convex_Post_Meta {

	/**
	 * Meta key for the Convex (or remote) document id after a post is synced.
	 */
	public const REMOTE_ID_META_KEY = 'post_to_convex_remote_id';

	/**
	 * Boot hooks.
	 */
	public static function init() {
		$self = new self();
		add_action( 'init', array( $self, 'register_meta' ) );
	}

	/**
	 * Register meta for all post types that participate in the REST block editor.
	 */
	public function register_meta() {
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
	public function auth_remote_id_meta( $allowed, $meta_key, $object_id ) {
		return (bool) current_user_can( 'edit_post', $object_id );
	}
}
