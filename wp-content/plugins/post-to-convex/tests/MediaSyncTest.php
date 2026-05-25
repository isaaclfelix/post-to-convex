<?php
/**
 * Unit tests for MediaSync helpers.
 *
 * @package Post_To_Convex
 */

declare( strict_types=1 );

namespace PostToConvex\Tests;

use PostToConvex\AdminSettings;
use PostToConvex\AttachmentMeta;
use PostToConvex\MediaSync;
use PostToConvex\SecretStore;
use WP_UnitTestCase;

/**
 * Tests MIME allowlist, cURL availability, and attachment field mapping.
 */
class MediaSyncTest extends WP_UnitTestCase {

	/**
	 * Allowed image MIME types are accepted.
	 *
	 * @return void
	 */
	public function test_is_allowed_mime_type_accepts_supported_images(): void {
		$this->assertTrue( MediaSync::is_allowed_mime_type( 'image/jpeg' ) );
		$this->assertTrue( MediaSync::is_allowed_mime_type( 'image/png' ) );
		$this->assertTrue( MediaSync::is_allowed_mime_type( 'image/webp' ) );
		$this->assertTrue( MediaSync::is_allowed_mime_type( 'image/gif' ) );
	}

	/**
	 * Non-image and unsupported MIME types are rejected.
	 *
	 * @return void
	 */
	public function test_is_allowed_mime_type_rejects_unsupported_types(): void {
		$this->assertFalse( MediaSync::is_allowed_mime_type( 'application/pdf' ) );
		$this->assertFalse( MediaSync::is_allowed_mime_type( 'image/svg+xml' ) );
		$this->assertFalse( MediaSync::is_allowed_mime_type( '' ) );
	}

	/**
	 * Attachment posts map to Convex multipart text fields.
	 *
	 * @return void
	 */
	public function test_get_attachment_form_fields_maps_post_properties(): void {
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Sample title',
				'post_excerpt'   => 'Sample caption',
				'post_content'   => 'Sample description',
			)
		);

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'Sample alt text' );

		$attachment = get_post( $attachment_id );
		$this->assertInstanceOf( \WP_Post::class, $attachment );

		$sync   = new MediaSync();
		$fields = $sync->get_attachment_form_fields( $attachment );

		$this->assertSame(
			array(
				'alt'         => 'Sample alt text',
				'title'       => 'Sample title',
				'caption'     => 'Sample caption',
				'description' => 'Sample description',
			),
			$fields
		);
	}

	/**
	 * Dimensions are read from attachment metadata.
	 *
	 * @return void
	 */
	public function test_get_attachment_dimensions_from_metadata(): void {
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);

		wp_update_attachment_metadata(
			$attachment_id,
			array(
				'width'  => 1920,
				'height' => 1080,
				'file'   => '2024/01/test.jpg',
			)
		);

		$sync = new MediaSync();
		$this->assertSame(
			array(
				'width'  => 1920,
				'height' => 1080,
			),
			$sync->get_attachment_dimensions( $attachment_id )
		);
	}

	/**
	 * Upload form fields include required width and height strings.
	 *
	 * @return void
	 */
	public function test_build_media_upload_form_fields_includes_dimensions(): void {
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Photo',
			)
		);

		wp_update_attachment_metadata(
			$attachment_id,
			array(
				'width'  => 800,
				'height' => 600,
				'file'   => '2024/01/photo.jpg',
			)
		);

		$attachment = get_post( $attachment_id );
		$this->assertInstanceOf( \WP_Post::class, $attachment );

		$sync   = new MediaSync();
		$fields = $sync->build_media_upload_form_fields( $attachment );

		$this->assertIsArray( $fields );
		$this->assertSame( '800', $fields['width'] );
		$this->assertSame( '600', $fields['height'] );
		$this->assertSame( 'Photo', $fields['title'] );
	}

	/**
	 * PATCH body includes mediaId, metadata strings, and pixel dimensions.
	 *
	 * @return void
	 */
	public function test_build_media_metadata_patch_body_includes_all_fields(): void {
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => '',
				'post_excerpt'   => '',
				'post_content'   => '',
			)
		);

		wp_update_attachment_metadata(
			$attachment_id,
			array(
				'width'  => 640,
				'height' => 480,
				'file'   => '2024/01/test.jpg',
			)
		);

		$attachment = get_post( $attachment_id );
		$this->assertInstanceOf( \WP_Post::class, $attachment );

		$sync = new MediaSync();
		$body = $sync->build_media_metadata_patch_body( $attachment, 'convexMedia123' );

		$this->assertSame(
			array(
				'mediaId'     => 'convexMedia123',
				'alt'         => '',
				'title'       => '',
				'caption'     => '',
				'description' => '',
				'width'       => 640,
				'height'      => 480,
			),
			$body
		);
		$this->assertSame(
			array( 'mediaId', 'alt', 'title', 'caption', 'description', 'width', 'height' ),
			array_keys( $body )
		);
	}

	/**
	 * PHPUnit environments for this plugin include the cURL extension.
	 *
	 * @return void
	 */
	public function test_is_curl_available_in_test_environment(): void {
		$this->assertTrue( MediaSync::is_curl_available() );
	}

	/**
	 * Unsupported MIME types return a block reason for manual sync.
	 *
	 * @return void
	 */
	public function test_get_sync_block_reason_rejects_pdf(): void {
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'application/pdf',
			)
		);

		$sync = new MediaSync();

		$this->assertNotNull( $sync->get_sync_block_reason( $attachment_id ) );
		$this->assertFalse( $sync->is_sync_eligible_attachment( $attachment_id ) );
	}

	/**
	 * Test detach_attachment_from_convex clears meta when Convex DELETE succeeds.
	 *
	 * @return void
	 */
	public function test_detach_attachment_from_convex_clears_meta_on_success(): void {
		$encrypted = SecretStore::encrypt( 'test-secret' );
		$this->assertNotSame( '', $encrypted, 'SecretStore encryption must work in PHPUnit.' );

		update_option( AdminSettings::OPTION_URL, 'https://example.convex.cloud' );
		update_option( AdminSettings::OPTION_SECRET, $encrypted );

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);

		update_post_meta( $attachment_id, AttachmentMeta::MEDIA_ID_META_KEY, 'mediaToDelete' );

		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $url ) {
				unset( $pre, $args );

				if ( ! is_string( $url ) || ! str_contains( $url, 'example.convex.cloud' ) ) {
					return false;
				}

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode( array( 'mediaId' => 'mediaToDelete' ) ),
				);
			},
			10,
			3
		);

		$sync   = new MediaSync();
		$result = $sync->detach_attachment_from_convex( $attachment_id );

		$this->assertTrue( $result['success'], $result['error'] ?? '' );
		$this->assertSame(
			'',
			get_post_meta( $attachment_id, AttachmentMeta::MEDIA_ID_META_KEY, true )
		);
	}

	/**
	 * Test sync_attachment_to_convex fails without calling HTTP for unsupported types.
	 *
	 * @return void
	 */
	public function test_sync_attachment_to_convex_fails_for_pdf(): void {
		$http_called = false;

		add_filter(
			'pre_http_request',
			static function () use ( &$http_called ) {
				$http_called = true;

				return false;
			}
		);

		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'application/pdf',
			)
		);

		$sync   = new MediaSync();
		$result = $sync->sync_attachment_to_convex( $attachment_id );

		$this->assertFalse( $result['success'] );
		$this->assertNull( $result['media_id'] );
		$this->assertIsString( $result['error'] );
		$this->assertFalse( $http_called );
	}
}
