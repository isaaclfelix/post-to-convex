<?php
/**
 * Unit tests for MediaSync helpers.
 *
 * @package Post_To_Convex
 */

declare( strict_types=1 );

namespace PostToConvex\Tests;

use PostToConvex\MediaSync;
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
	 * PHPUnit environments for this plugin include the cURL extension.
	 *
	 * @return void
	 */
	public function test_is_curl_available_in_test_environment(): void {
		$this->assertTrue( MediaSync::is_curl_available() );
	}
}
