<?php
/**
 * Registers attachment meta for Convex media ids.
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
 * Attachment meta keys for Post to Convex.
 */
class AttachmentMeta {

	/**
	 * Meta key for the Convex media document id after an attachment is synced.
	 *
	 * @var string
	 */
	public const MEDIA_ID_META_KEY = 'post_to_convex_media_id';

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
	 * Register meta for media attachments.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		register_post_meta(
			'attachment',
			self::MEDIA_ID_META_KEY,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( $this, 'auth_media_id_meta' ),
			)
		);
	}

	/**
	 * Limit meta visibility and edits to users who can edit the attachment.
	 *
	 * @param bool   $allowed   Whether the user can add this meta.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Attachment post ID.
	 * @return bool
	 */
	public function auth_media_id_meta( bool $allowed, string $meta_key, int $object_id ): bool {
		return (bool) current_user_can( 'edit_post', $object_id );
	}
}
