<?php
/**
 * End-to-end tests for the core/heading translator.
 *
 * @package Post_To_Convex
 */

declare( strict_types=1 );

namespace PostToConvex\Tests;

use PostToConvex\BlockHandlers\HeadingHandler;
use WP_UnitTestCase;
use PostToConvex\BlockHandlers\InlineTreeParser;
use PostToConvex\BlockHandlers\PresetResolver;

/**
 * Drives the heading handler through every variant in
 * `tests/data/sample-heading-block-variants.html` so each block-attribute and
 * inline-content concern has at least one explicit assertion.
 *
 * Tests are grouped by concern (level / align / colors / typography / spacing
 * / inline content) so a regression in any one area produces a localized
 * failure.
 */
class HeadingHandlerTest extends WP_UnitTestCase {

	/**
	 * Cached parsed sample blocks (filled lazily on first access).
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private static ?array $sample_blocks = null;

	/**
	 * Heading handler under test.
	 *
	 * @var HeadingHandler
	 */
	private HeadingHandler $handler;

	/**
	 * Fresh handler with a deterministic stub resolver before each test.
	 *
	 * The stub bypasses WordPress' theme.json merge logic so heading-handler
	 * assertions stay focused on translator behaviour, not on whichever
	 * theme.json the test environment happens to load. Resolver behaviour
	 * against the real WP integration is covered separately in
	 * `PresetResolverTest.php`.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->handler = new HeadingHandler( new InlineTreeParser(), $this->make_fake_resolver() );
	}

	/**
	 * Build a stub `PresetResolver` that returns canned values for the
	 * presets used in the sample HTML.
	 *
	 * @return PresetResolver Stub resolver.
	 */
	private function make_fake_resolver(): PresetResolver {
		return new class() extends PresetResolver {

			/**
			 * Resolve a color preset slug from a fixed test palette.
			 *
			 * @param string $slug Color slug.
			 * @return string|null Resolved hex value or null.
			 */
			public function resolve_color( string $slug ): ?string {
				$palette = array(
					'vivid-red'      => '#cf2e2e',
					'pale-cyan-blue' => '#abb8c3',
					'white'          => '#ffffff',
				);

				return $palette[ $slug ] ?? null;
			}

			/**
			 * Resolve a font-size preset slug from a fixed test scale.
			 *
			 * @param string $slug Font-size slug.
			 * @return string|null Resolved CSS value or null.
			 */
			public function resolve_font_size( string $slug ): ?string {
				$sizes = array(
					'small'   => '13px',
					'medium'  => '20px',
					'large'   => '36px',
					'x-large' => '42px',
				);

				return $sizes[ $slug ] ?? null;
			}

			/**
			 * Resolve a spacing preset slug from a fixed test scale.
			 *
			 * @param string $slug Spacing slug.
			 * @return string|null Resolved CSS value or null.
			 */
			public function resolve_spacing( string $slug ): ?string {
				return '50' === $slug ? '1.25rem' : null;
			}
		};
	}

	/**
	 * Lazily load and parse the sample HTML, flattening any nested heading
	 * blocks (e.g. inside the trailing flex `core/group`) so the index-based
	 * lookup matches the source file's reading order.
	 *
	 * @return array<int, array<string, mixed>> All heading blocks in the sample, in document order.
	 */
	private function sample_blocks(): array {
		if ( null !== self::$sample_blocks ) {
			return self::$sample_blocks;
		}

		$path   = __DIR__ . '/data/sample-heading-block-variants.html';
		$html   = file_exists( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture read; wp_remote_get is for URLs.
		$blocks = parse_blocks( is_string( $html ) ? $html : '' );

		self::$sample_blocks = $this->flatten_heading_blocks( $blocks );

		return self::$sample_blocks;
	}

	/**
	 * Recursively collect every `core/heading` block (including those nested
	 * inside wrapper blocks like `core/group`) in document order.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array<string, mixed>> Heading blocks only.
	 */
	private function flatten_heading_blocks( array $blocks ): array {
		$out = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( 'core/heading' === ( $block['blockName'] ?? null ) ) {
				$out[] = $block;
				continue;
			}
			$inner = $block['innerBlocks'] ?? null;
			if ( is_array( $inner ) && ! empty( $inner ) ) {
				foreach ( $this->flatten_heading_blocks( $inner ) as $nested ) {
					$out[] = $nested;
				}
			}
		}

		return $out;
	}

	/**
	 * Translate a heading block by its index in the (flattened) sample.
	 *
	 * @param int $index Index into the flattened sample blocks.
	 * @return array<string, mixed> Translated block.
	 */
	private function translate_index( int $index ): array {
		$blocks = $this->sample_blocks();
		$this->assertArrayHasKey(
			$index,
			$blocks,
			sprintf( 'Sample HTML missing block at index %d', $index )
		);

		return $this->handler->translate( $blocks[ $index ] );
	}

	/**
	 * H1-H6 levels (and the no-attrs default of H2) round-trip correctly.
	 *
	 * @return void
	 */
	public function test_levels_h1_through_h6(): void {
		// First five samples are all H1 (plain, bold, italic, linked, highlighted).
		$this->assertSame( 1, $this->translate_index( 0 )['level'] );
		$this->assertSame( 1, $this->translate_index( 1 )['level'] );
		$this->assertSame( 1, $this->translate_index( 2 )['level'] );
		$this->assertSame( 1, $this->translate_index( 3 )['level'] );
		$this->assertSame( 1, $this->translate_index( 4 )['level'] );

		// Index 5 is the no-attrs `<!-- wp:heading -->` (defaults to H2).
		$this->assertSame( 2, $this->translate_index( 5 )['level'] );

		// Indexes 6-9 are H3-H6.
		$this->assertSame( 3, $this->translate_index( 6 )['level'] );
		$this->assertSame( 4, $this->translate_index( 7 )['level'] );
		$this->assertSame( 5, $this->translate_index( 8 )['level'] );
		$this->assertSame( 6, $this->translate_index( 9 )['level'] );
	}

	/**
	 * Inline content for the H1 variants (plain / bold / italic / linked /
	 * highlighted) parses into the canonical AST.
	 *
	 * @return void
	 */
	public function test_inline_bold_italic_link_mark(): void {
		$this->assertSame(
			array(
				array(
					'type' => 'text',
					'text' => 'H1 Heading',
				),
			),
			$this->translate_index( 0 )['content']
		);

		$this->assertSame(
			array(
				array(
					'type'     => 'strong',
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'H1 Heading, bold',
						),
					),
				),
			),
			$this->translate_index( 1 )['content']
		);

		$this->assertSame(
			array(
				array(
					'type'     => 'em',
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'H1 Heading, italic',
						),
					),
				),
			),
			$this->translate_index( 2 )['content']
		);

		$this->assertSame(
			array(
				array(
					'type'     => 'link',
					'attrs'    => array( 'href' => '/' ),
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'H1 Heading, linked',
						),
					),
				),
			),
			$this->translate_index( 3 )['content']
		);

		$mark_content = $this->translate_index( 4 )['content'];
		$this->assertCount( 1, $mark_content );
		$this->assertSame( 'mark', $mark_content[0]['type'] );
		$this->assertTrue( $mark_content[0]['attrs']['hasInlineColor'] );
		$this->assertSame(
			array(
				'backgroundColor' => '#6e00ff',
				'color'           => '#06eff7',
			),
			$mark_content[0]['attrs']['style']
		);
		$this->assertSame(
			array(
				array(
					'type' => 'text',
					'text' => 'H1 Heading, highlighted',
				),
			),
			$mark_content[0]['children']
		);
	}

	/**
	 * `align: wide` and `align: full` survive translation.
	 *
	 * @return void
	 */
	public function test_align_wide_and_full(): void {
		$this->assertSame( 'wide', $this->translate_index( 10 )['align'] );
		$this->assertSame( 'full', $this->translate_index( 11 )['align'] );
	}

	/**
	 * `textAlign: left | center | right` survives translation.
	 *
	 * @return void
	 */
	public function test_text_align(): void {
		$this->assertSame( 'left', $this->translate_index( 12 )['textAlign'] );
		$this->assertSame( 'center', $this->translate_index( 13 )['textAlign'] );
		$this->assertSame( 'right', $this->translate_index( 14 )['textAlign'] );
	}

	/**
	 * `textColor` only — text preset resolves; background/link stay null.
	 *
	 * @return void
	 */
	public function test_color_text_only(): void {
		$colors = $this->translate_index( 15 )['colors'];

		$this->assertSame(
			array(
				'token'    => 'vivid-red',
				'resolved' => '#cf2e2e',
			),
			$colors['text']
		);
		$this->assertNull( $colors['background'] );
		$this->assertSame(
			array(
				'token'    => 'vivid-red',
				'resolved' => '#cf2e2e',
			),
			$colors['link']
		);
	}

	/**
	 * `textColor` + `backgroundColor` resolve through the palette.
	 *
	 * @return void
	 */
	public function test_color_text_and_background(): void {
		$colors = $this->translate_index( 16 )['colors'];

		$this->assertSame(
			array(
				'token'    => 'vivid-red',
				'resolved' => '#cf2e2e',
			),
			$colors['text']
		);
		$this->assertSame(
			array(
				'token'    => 'pale-cyan-blue',
				'resolved' => '#abb8c3',
			),
			$colors['background']
		);
		$this->assertSame(
			array(
				'token'    => 'vivid-red',
				'resolved' => '#cf2e2e',
			),
			$colors['link']
		);
	}

	/**
	 * Independent text / background / link colors all resolve.
	 *
	 * @return void
	 */
	public function test_color_text_background_and_link(): void {
		$colors = $this->translate_index( 17 )['colors'];

		$this->assertSame(
			array(
				'token'    => 'vivid-red',
				'resolved' => '#cf2e2e',
			),
			$colors['text']
		);
		$this->assertSame(
			array(
				'token'    => 'pale-cyan-blue',
				'resolved' => '#abb8c3',
			),
			$colors['background']
		);
		$this->assertSame(
			array(
				'token'    => 'white',
				'resolved' => '#ffffff',
			),
			$colors['link']
		);
	}

	/**
	 * Font-size presets carry both the slug token and resolved CSS value.
	 *
	 * @return void
	 */
	public function test_font_size_presets(): void {
		$this->assertSame(
			array(
				'token'    => 'small',
				'resolved' => '13px',
			),
			$this->translate_index( 18 )['typography']['fontSize']
		);
		$this->assertSame(
			array(
				'token'    => 'medium',
				'resolved' => '20px',
			),
			$this->translate_index( 19 )['typography']['fontSize']
		);
		$this->assertSame(
			array(
				'token'    => 'large',
				'resolved' => '36px',
			),
			$this->translate_index( 20 )['typography']['fontSize']
		);
		$this->assertSame(
			array(
				'token'    => 'x-large',
				'resolved' => '42px',
			),
			$this->translate_index( 21 )['typography']['fontSize']
		);
	}

	/**
	 * Custom typography (font-style + font-weight) carries through.
	 *
	 * @return void
	 */
	public function test_typography_font_style_and_weight(): void {
		$light = $this->translate_index( 22 )['typography'];
		$this->assertSame( 'italic', $light['fontStyle'] );
		$this->assertSame( '200', $light['fontWeight'] );

		$black = $this->translate_index( 23 )['typography'];
		$this->assertSame( 'italic', $black['fontStyle'] );
		$this->assertSame( '900', $black['fontWeight'] );
	}

	/**
	 * Line-height and letter-spacing carry through as literal CSS values.
	 *
	 * @return void
	 */
	public function test_typography_line_height_and_letter_spacing(): void {
		$this->assertSame( '2.4', $this->translate_index( 24 )['typography']['lineHeight'] );
		$this->assertSame( '7px', $this->translate_index( 25 )['typography']['letterSpacing'] );
	}

	/**
	 * Underline and strikethrough text-decoration values carry through.
	 *
	 * @return void
	 */
	public function test_typography_text_decoration(): void {
		$this->assertSame(
			'underline',
			$this->translate_index( 26 )['typography']['textDecoration']
		);
		$this->assertSame(
			'line-through',
			$this->translate_index( 27 )['typography']['textDecoration']
		);
	}

	/**
	 * Uppercase / lowercase / capitalize text-transform values carry through.
	 *
	 * @return void
	 */
	public function test_typography_text_transform(): void {
		$this->assertSame(
			'uppercase',
			$this->translate_index( 28 )['typography']['textTransform']
		);
		$this->assertSame(
			'lowercase',
			$this->translate_index( 29 )['typography']['textTransform']
		);
		$this->assertSame(
			'capitalize',
			$this->translate_index( 30 )['typography']['textTransform']
		);
	}

	/**
	 * Spacing presets resolve through the spacing palette and emit
	 * `{ token, resolved }` for every side.
	 *
	 * @return void
	 */
	public function test_spacing_padding_preset(): void {
		$padding = $this->translate_index( 31 )['spacing']['padding'];

		$expected_side = array(
			'token'    => '50',
			'resolved' => '1.25rem',
		);

		$this->assertSame( $expected_side, $padding['top'] );
		$this->assertSame( $expected_side, $padding['right'] );
		$this->assertSame( $expected_side, $padding['bottom'] );
		$this->assertSame( $expected_side, $padding['left'] );

		$this->assertNull( $this->translate_index( 31 )['spacing']['margin'] );
	}

	/**
	 * Margin presets resolve identically to padding presets.
	 *
	 * @return void
	 */
	public function test_spacing_margin_preset(): void {
		$margin = $this->translate_index( 32 )['spacing']['margin'];

		$expected_side = array(
			'token'    => '50',
			'resolved' => '1.25rem',
		);

		$this->assertSame( $expected_side, $margin['top'] );
		$this->assertSame( $expected_side, $margin['right'] );
		$this->assertSame( $expected_side, $margin['bottom'] );
		$this->assertSame( $expected_side, $margin['left'] );

		$this->assertNull( $this->translate_index( 32 )['spacing']['padding'] );
	}

	/**
	 * The two vertical headings nested inside the trailing core/group are
	 * surfaced in document order with their `writingMode` and `textAlign`
	 * preserved.
	 *
	 * @return void
	 */
	public function test_writing_mode_vertical_rl(): void {
		$blocks = $this->sample_blocks();
		$this->assertGreaterThanOrEqual(
			35,
			count( $blocks ),
			'Sample is expected to include the two vertical headings nested in core/group.'
		);

		$left  = $this->handler->translate( $blocks[33] );
		$right = $this->handler->translate( $blocks[34] );

		$this->assertSame( 'left', $left['textAlign'] );
		$this->assertSame( 'vertical-rl', $left['typography']['writingMode'] );

		$this->assertSame( 'right', $right['textAlign'] );
		$this->assertSame( 'vertical-rl', $right['typography']['writingMode'] );
	}
}
