<?php
/**
 * Unit tests for AttachmentFields screen gating and compat markup.
 *
 * @package Post_To_Convex
 */

declare( strict_types=1 );

namespace PostToConvex\Tests;

use PostToConvex\AttachmentFields;
use PostToConvex\AttachmentMeta;
use WP_UnitTestCase;

/**
 * Tests AttachmentFields filter behavior.
 */
class AttachmentFieldsTest extends WP_UnitTestCase {

	/**
	 * Filter adds Convex field on the Media Library upload screen.
	 *
	 * @return void
	 */
	public function test_filter_adds_field_on_upload_screen(): void {
		set_current_screen( 'upload' );

		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);

		$attachment = get_post( $attachment_id );
		$this->assertInstanceOf( \WP_Post::class, $attachment );

		$fields = ( new AttachmentFields() )->filter_attachment_fields_to_edit( array(), $attachment );

		$this->assertArrayHasKey( 'post_to_convex', $fields );
		$this->assertStringContainsString(
			'post-to-convex-media-mount',
			$fields['post_to_convex']['html']
		);
		$this->assertStringContainsString(
			'data-attachment-id="' . (string) $attachment_id . '"',
			$fields['post_to_convex']['html']
		);
	}

	/**
	 * Filter skips post edit screens (Insert Media modal on posts).
	 *
	 * @return void
	 */
	public function test_filter_skips_post_edit_screen(): void {
		set_current_screen( 'post' );

		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);

		$attachment = get_post( $attachment_id );
		$this->assertInstanceOf( \WP_Post::class, $attachment );

		$fields = ( new AttachmentFields() )->filter_attachment_fields_to_edit( array(), $attachment );

		$this->assertSame( array(), $fields );
	}

	/**
	 * Filter adds field on classic attachment post edit screen.
	 *
	 * @return void
	 */
	public function test_filter_adds_field_on_attachment_post_screen(): void {
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);

		set_current_screen( 'attachment' );

		$attachment = get_post( $attachment_id );
		$this->assertInstanceOf( \WP_Post::class, $attachment );

		update_post_meta( $attachment_id, AttachmentMeta::MEDIA_ID_META_KEY, 'convex123' );

		$fields = ( new AttachmentFields() )->filter_attachment_fields_to_edit( array(), $attachment );

		$this->assertArrayHasKey( 'post_to_convex', $fields );
		$this->assertStringNotContainsString(
			'data-convex-id',
			$fields['post_to_convex']['html']
		);
	}

	/**
	 * Filter adds Convex field during Media Library grid AJAX (no current screen).
	 *
	 * @return void
	 */
	public function test_filter_adds_field_during_media_library_ajax(): void {
		global $current_screen;

		$previous_screen = $current_screen;
		$current_screen  = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		add_filter( 'wp_doing_ajax', '__return_true' );

		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);

		$attachment = get_post( $attachment_id );
		$this->assertInstanceOf( \WP_Post::class, $attachment );

		$fields     = array();
		$run_filter = static function () use ( $attachment, &$fields ): void {
			$fields = ( new AttachmentFields() )->filter_attachment_fields_to_edit( array(), $attachment );
		};

		add_action( 'wp_ajax_query-attachments', $run_filter, 1 );
		do_action( 'wp_ajax_query-attachments' ); // phpcs:ignore
		remove_action( 'wp_ajax_query-attachments', $run_filter, 1 );

		$current_screen = $previous_screen; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		remove_filter( 'wp_doing_ajax', '__return_true' );

		$this->assertArrayHasKey( 'post_to_convex', $fields );
	}

	/**
	 * Filter skips Insert Media AJAX when the edited parent is a post.
	 *
	 * @return void
	 */
	public function test_filter_skips_insert_media_ajax_on_post(): void {
		global $current_screen;

		$previous_screen = $current_screen;
		$current_screen  = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$post_id = self::factory()->post->create();

		$_REQUEST['post_id'] = $post_id;
		add_filter( 'wp_doing_ajax', '__return_true' );

		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);

		$attachment = get_post( $attachment_id );
		$this->assertInstanceOf( \WP_Post::class, $attachment );

		$fields     = array();
		$run_filter = static function () use ( $attachment, &$fields ): void {
			$fields = ( new AttachmentFields() )->filter_attachment_fields_to_edit( array(), $attachment );
		};

		add_action( 'wp_ajax_query-attachments', $run_filter, 1 );
		do_action( 'wp_ajax_query-attachments' ); // phpcs:ignore
		remove_action( 'wp_ajax_query-attachments', $run_filter, 1 );

		$current_screen = $previous_screen; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		unset( $_REQUEST['post_id'] );
		remove_filter( 'wp_doing_ajax', '__return_true' );

		$this->assertSame( array(), $fields );
	}
}
