<?php
/**
 * End-to-end tests for the core/image translator.
 *
 * @package Post_To_Convex
 */

declare( strict_types=1 );

namespace PostToConvex\Tests;

use PostToConvex\BlockHandlers\ImageHandler;
use PostToConvex\BlockHandlers\InlineTreeParser;
use PostToConvex\BlockTranslationException;
use PostToConvex\MediaSync;
use PostToConvex\Tests\Support\BlockHandlerTestSupport;
use WP_UnitTestCase;

/**
 * Drives the image handler through every variant in
 * `tests/data/sample-image-block-variants.html`.
 */
class ImageHandlerTest extends WP_UnitTestCase {

	use BlockHandlerTestSupport;

	/**
	 * Sample HTML fixture name.
	 *
	 * @var string
	 */
	private const SAMPLE_FILE = 'sample-image-block-variants.html';

	/**
	 * Convex media id returned by the default stub.
	 *
	 * @var string
	 */
	private const STUB_MEDIA_ID = 'sample-convex-media-id';

	/**
	 * Cached flattened image blocks (filled lazily on first access).
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private static ?array $sample_blocks = null;

	/**
	 * Image handler under test.
	 *
	 * @var ImageHandler
	 */
	private ImageHandler $handler;

	/**
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$resolver = $this->make_fake_resolver(
			array(
				'vivid-red' => '#cf2e2e',
			)
		);

		$this->handler = new ImageHandler(
			new InlineTreeParser(),
			$resolver,
			new StubMediaSync( self::STUB_MEDIA_ID )
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function sample_blocks(): array {
		if ( null === self::$sample_blocks ) {
			self::$sample_blocks = $this->load_blocks_of_type( self::SAMPLE_FILE, 'core/image' );
		}

		return self::$sample_blocks;
	}

	/**
	 * @param int $index Index into the flattened sample blocks.
	 * @return array<string, mixed>
	 */
	private function translate_index( int $index ): array {
		$blocks = $this->sample_blocks();
		$this->assertArrayHasKey(
			$index,
			$blocks,
			sprintf( 'Sample HTML missing image block at index %d', $index )
		);

		return $this->handler->translate( $blocks[ $index ] );
	}

	/**
	 * @param array<int, array<string, mixed>> $caption Inline caption AST.
	 * @return string Plain caption text from the first text leaf.
	 */
	private function caption_text( array $caption ): string {
		if ( array() === $caption ) {
			return '';
		}

		$first = $caption[0];

		return is_array( $first ) && 'text' === ( $first['type'] ?? null ) && is_string( $first['text'] ?? null )
			? $first['text']
			: '';
	}

	/**
	 * @return void
	 */
	public function test_sample_fixture_has_expected_block_count(): void {
		$this->assertCount( 28, $this->sample_blocks() );
	}

	/**
	 * @return void
	 */
	public function test_block_name_is_core_image(): void {
		$this->assertSame( 'core/image', $this->translate_index( 0 )['blockName'] );
	}

	/**
	 * @return void
	 */
	public function test_media_id_populated_from_media_sync(): void {
		$this->assertSame( self::STUB_MEDIA_ID, $this->translate_index( 0 )['mediaId'] );
	}

	/**
	 * @return void
	 */
	public function test_align_null_by_default(): void {
		$this->assertNull( $this->translate_index( 0 )['align'] );
	}

	/**
	 * @return void
	 */
	public function test_align_left_center_right_wide_full(): void {
		$expected = array(
			1 => 'left',
			2 => 'center',
			3 => 'right',
			4 => 'wide',
			5 => 'full',
		);

		foreach ( $expected as $index => $align ) {
			$this->assertSame( $align, $this->translate_index( $index )['align'], "index $index" );
		}
	}

	/**
	 * @return void
	 */
	public function test_caption_parsed_from_figcaption(): void {
		$this->assertSame( 'Align left', $this->caption_text( $this->translate_index( 1 )['caption'] ) );
		$this->assertSame( 'Wide', $this->caption_text( $this->translate_index( 4 )['caption'] ) );
		$this->assertSame( 'Rounded variation', $this->caption_text( $this->translate_index( 6 )['caption'] ) );
	}

	/**
	 * @return void
	 */
	public function test_caption_empty_when_absent(): void {
		$this->assertSame( array(), $this->translate_index( 0 )['caption'] );
		$this->assertSame( array(), $this->translate_index( 15 )['caption'] );
	}

	/**
	 * @return void
	 */
	public function test_class_name_rounded_variation(): void {
		$this->assertSame( 'is-style-rounded', $this->translate_index( 6 )['className'] );
	}

	/**
	 * @return void
	 */
	public function test_size_slug_present(): void {
		$this->assertSame( 'large', $this->translate_index( 0 )['sizeSlug'] );
		$this->assertSame( 'full', $this->translate_index( 8 )['sizeSlug'] );
		$this->assertSame( 'large', $this->translate_index( 15 )['sizeSlug'] );
	}

	/**
	 * @return void
	 */
	public function test_width_and_height_string_attrs(): void {
		$block = $this->translate_index( 12 );
		$this->assertSame( '840px', $block['width'] );
		$this->assertSame( 'auto', $block['height'] );

		$this->assertSame( '300px', $this->translate_index( 15 )['width'] );
		$this->assertSame( '300px', $this->translate_index( 16 )['width'] );
		$this->assertSame( '300px', $this->translate_index( 17 )['width'] );
	}

	/**
	 * @return void
	 */
	public function test_aspect_ratio_and_scale(): void {
		$this->assertSame( '1', $this->translate_index( 8 )['aspectRatio'] );
		$this->assertSame( 'cover', $this->translate_index( 8 )['scale'] );
		$this->assertSame( '4/3', $this->translate_index( 9 )['aspectRatio'] );
		$this->assertSame( '3/4', $this->translate_index( 10 )['aspectRatio'] );
		$this->assertSame( '3/2', $this->translate_index( 11 )['aspectRatio'] );
		$this->assertSame( '16/9', $this->translate_index( 13 )['aspectRatio'] );
		$this->assertSame( '9/16', $this->translate_index( 14 )['aspectRatio'] );
	}

	/**
	 * @return void
	 */
	public function test_lightbox_object(): void {
		$this->assertSame(
			array( 'enabled' => true ),
			$this->translate_index( 7 )['lightbox']
		);
		$this->assertSame(
			array( 'enabled' => false ),
			$this->translate_index( 8 )['lightbox']
		);
		$this->assertSame(
			array( 'enabled' => true ),
			$this->translate_index( 15 )['lightbox']
		);
	}

	/**
	 * @return void
	 */
	public function test_link_destination_none(): void {
		$link = $this->translate_index( 0 )['link'];

		$this->assertSame( 'none', $link['destination'] );
		$this->assertNull( $link['url'] );
	}

	/**
	 * @return void
	 */
	public function test_border_color_preset(): void {
		$translated = $this->translate_index( 16 );

		$this->assertSame(
			array(
				'token'    => 'vivid-red',
				'resolved' => '#cf2e2e',
			),
			$translated['colors']['border']
		);
		$this->assertSame( '5px', $translated['border']['width'] );
	}

	/**
	 * @return void
	 */
	public function test_custom_border_color_and_radius(): void {
		$block_17 = $this->translate_index( 17 );
		$this->assertSame( '50px', $block_17['border']['radius'] );
		$this->assertSame(
			array(
				'token'    => null,
				'resolved' => '#cf2e2e',
			),
			$block_17['border']['color']
		);

		$block_18 = $this->translate_index( 18 );
		$this->assertSame(
			array(
				'token'    => null,
				'resolved' => '#2dbecf',
			),
			$block_18['border']['color']
		);
	}

	/**
	 * @return void
	 */
	public function test_duotone_tokens_all_presets(): void {
		$expected = array(
			19 => 'dark-grayscale',
			20 => 'grayscale',
			21 => 'purple-yellow',
			22 => 'blue-red',
			23 => 'midnight',
			24 => 'magenta-yellow',
			25 => 'purple-green',
			26 => 'blue-orange',
		);

		foreach ( $expected as $index => $slug ) {
			$duotone = $this->translate_index( $index )['colors']['duotone'];
			$this->assertIsArray( $duotone, "index $index" );
			$this->assertSame( $slug, $duotone['token'], "index $index" );
			$this->assertNull( $duotone['resolved'], "index $index" );
		}
	}

	/**
	 * @return void
	 */
	public function test_ast_includes_all_top_level_keys(): void {
		$translated = $this->translate_index( 0 );

		foreach (
			array(
				'blockName',
				'mediaId',
				'alt',
				'caption',
				'align',
				'className',
				'sizeSlug',
				'width',
				'height',
				'aspectRatio',
				'scale',
				'lightbox',
				'link',
				'colors',
				'spacing',
				'border',
			) as $key
		) {
			$this->assertArrayHasKey( $key, $translated, $key );
		}

		foreach ( array( 'text', 'background', 'link', 'border', 'duotone' ) as $color_key ) {
			$this->assertArrayHasKey( $color_key, $translated['colors'], $color_key );
		}
	}

	/**
	 * @return void
	 */
	public function test_rejects_block_without_attachment_id(): void {
		$this->expectException( BlockTranslationException::class );

		$this->translate_index( 27 );
	}

	/**
	 * @return void
	 */
	public function test_rejects_when_media_sync_returns_null(): void {
		$resolver = $this->make_fake_resolver();
		$handler  = new ImageHandler(
			new InlineTreeParser(),
			$resolver,
			new StubMediaSync( null, __( 'Sync blocked for test.', 'post-to-convex' ) )
		);

		$this->expectException( BlockTranslationException::class );
		$this->expectExceptionMessage( 'Sync blocked for test.' );

		$handler->translate(
			array(
				'blockName' => 'core/image',
				'attrs'     => array( 'id' => 1 ),
				'innerHTML' => '<figure class="wp-block-image"><img alt="" class="wp-image-1"/></figure>',
			)
		);
	}
}

/**
 * Stub MediaSync for handler unit tests.
 */
final class StubMediaSync extends MediaSync {

	/**
	 * @param string|null $media_id     Value for ensure_attachment_synced.
	 * @param string|null $block_reason Value for get_sync_block_reason.
	 */
	public function __construct(
		private readonly ?string $media_id,
		private readonly ?string $block_reason = null
	) {
	}

	/**
	 * @param int $attachment_id WordPress attachment post ID.
	 * @return string|null
	 */
	public function ensure_attachment_synced( int $attachment_id ): ?string {
		unset( $attachment_id );

		return $this->media_id;
	}

	/**
	 * @param int $attachment_id WordPress attachment post ID.
	 * @return string|null
	 */
	public function get_sync_block_reason( int $attachment_id ): ?string {
		unset( $attachment_id );

		return $this->block_reason;
	}
}
