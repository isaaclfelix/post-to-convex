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
 * Tests MIME allowlist, multipart body construction, and attachment field mapping.
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
	 * Multipart body includes ordered text fields and a file part.
	 *
	 * @return void
	 */
	public function test_build_multipart_body_includes_fields_and_file_part(): void {
		$file_path = wp_tempnam( 'media-sync-test' );
		$this->assertNotFalse( $file_path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture write.
		file_put_contents( $file_path, 'fake-image-bytes' );

		$sync = new MediaSync();
		$body = $sync->build_multipart_body(
			array(
				'alt'         => 'Alt',
				'title'       => 'Title',
				'caption'     => 'Caption',
				'description' => 'Description',
			),
			$file_path,
			'image/jpeg',
			'test-image.jpg'
		);

		wp_delete_file( $file_path );

		$this->assertArrayHasKey( 'body', $body );
		$this->assertArrayHasKey( 'boundary', $body );
		$this->assertNotSame( '', $body['boundary'] );

		$payload = $body['body'];

		$this->assertStringContainsString( 'name="alt"', $payload );
		$this->assertStringContainsString( "Alt\r\n", $payload );
		$this->assertStringContainsString( 'name="title"', $payload );
		$this->assertStringContainsString( "Title\r\n", $payload );
		$this->assertStringContainsString( 'name="caption"', $payload );
		$this->assertStringContainsString( "Caption\r\n", $payload );
		$this->assertStringContainsString( 'name="description"', $payload );
		$this->assertStringContainsString( "Description\r\n", $payload );
		$this->assertStringContainsString( 'name="file"; filename="test-image.jpg"', $payload );
		$this->assertStringContainsString( 'Content-Type: image/jpeg', $payload );
		$this->assertStringContainsString( 'fake-image-bytes', $payload );
		$this->assertStringEndsWith( '--' . $body['boundary'] . "--\r\n", $payload );

		$alt_position   = strpos( $payload, 'name="alt"' );
		$title_position = strpos( $payload, 'name="title"' );
		$file_position  = strpos( $payload, 'name="file"' );

		$this->assertNotFalse( $alt_position );
		$this->assertNotFalse( $title_position );
		$this->assertNotFalse( $file_position );
		$this->assertLessThan( $title_position, $alt_position );
		$this->assertLessThan( $file_position, $title_position );
	}
}
