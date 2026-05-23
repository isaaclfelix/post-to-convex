<?php
/**
 * Registers term meta.
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
 * Term meta keys for Post to Convex.
 */
class TermMeta {

	/**
	 * Meta key for the Convex (or remote) document id after a term is synced.
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
	 * Register meta for all taxonomies.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		$taxonomies = get_taxonomies();

		foreach ( $taxonomies as $taxonomy ) {
			register_term_meta(
				$taxonomy,
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
	 * Limit meta visibility and edits to users who can edit the terms.
	 *
	 * @param bool   $allowed   Whether the user can add or edit the terms.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Post ID.
	 * @return bool
	 */
	public function auth_remote_id_meta( bool $allowed, string $meta_key, int $object_id ): bool {
		return (bool) current_user_can( 'edit_term', $object_id );
	}
}
