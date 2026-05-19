<?php
/**
 * End-to-end tests for the core/heading translator.
 *
 * @package Post_To_Convex
 */

declare( strict_types=1 );

namespace PostToConvex\Tests;

use PostToConvex\BlockHandlers\HeadingHandler;
use PostToConvex\BlockHandlers\InlineTreeParser;
use PostToConvex\Tests\Support\BlockHandlerTestSupport;
use WP_UnitTestCase;

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

	use BlockHandlerTestSupport;

	/**
	 * Sample HTML fixture name.
	 *
	 * @var string
	 */
	private const SAMPLE_FILE = 'sample-heading-block-variants.html';

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

		$resolver = $this->make_fake_resolver(
			array(
				'vivid-red'      => '#cf2e2e',
				'pale-cyan-blue' => '#abb8c3',
				'white'          => '#ffffff',
			),
			array(
				'small'   => '13px',
				'medium'  => '20px',
				'large'   => '36px',
				'x-large' => '42px',
			),
			array( '50' => '1.25rem' )
		);

		$this->handler = new HeadingHandler( new InlineTreeParser(), $resolver );
	}

	/**
	 * Lazily load and flatten every heading block in the sample (including
	 * those nested inside wrapper blocks like the trailing `core/group`).
	 *
	 * @return array<int, array<string, mixed>> Heading blocks in document order.
	 */
	private function sample_blocks(): array {
		if ( null === self::$sample_blocks ) {
			self::$sample_blocks = $this->load_blocks_of_type( self::SAMPLE_FILE, 'core/heading' );
		}

		return self::$sample_blocks;
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

	/**
	 * All six valid orderings of a "bold italic linked" inline run.
	 *
	 * Mirrors {@see InlineTreeParserTest::provide_link_strong_em_orderings}.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function provide_link_strong_em_orderings(): array {
		return array(
			'a > strong > em' => array( '<a href="/x"><strong><em>x</em></strong></a>' ),
			'a > em > strong' => array( '<a href="/x"><em><strong>x</strong></em></a>' ),
			'strong > a > em' => array( '<strong><a href="/x"><em>x</a></em></strong>' ),
			'strong > em > a' => array( '<strong><em><a href="/x">x</a></em></strong>' ),
			'em > a > strong' => array( '<em><a href="/x"><strong>x</strong></a></em>' ),
			'em > strong > a' => array( '<em><strong><a href="/x">x</a></strong></em>' ),
		);
	}

	/**
	 * The canonical AST for "bold italic linked" inline content.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function canonical_link_strong_em_tree(): array {
		return array(
			array(
				'type'     => 'link',
				'attrs'    => array( 'href' => '/x' ),
				'children' => array(
					array(
						'type'     => 'strong',
						'children' => array(
							array(
								'type'     => 'em',
								'children' => array(
									array(
										'type' => 'text',
										'text' => 'x',
									),
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Integration-level guard: every author-ordering of a triple-marked inline
	 * run (link / strong / em around an "x" leaf), routed through a synthetic
	 * `core/heading` block and {@see HeadingHandler::translate()}, collapses
	 * to the canonical `link > strong > em > text` AST.
	 *
	 * The parser-level guarantee is exercised in
	 * {@see InlineTreeParserTest::test_canonicalization_collapses_all_orderings}.
	 * This test confirms the canonicalization survives the handler-level
	 * wiring for headings specifically (and mirrors the matching guard in
	 * {@see ParagraphHandlerTest}).
	 *
	 * @dataProvider provide_link_strong_em_orderings
	 *
	 * @param string $ordering_html Inline HTML for one of the six valid orderings.
	 * @return void
	 */
	public function test_inline_content_canonicalization_collapses_all_orderings( string $ordering_html ): void {
		$block = array(
			'blockName'   => 'core/heading',
			'attrs'       => array(),
			'innerHTML'   => '<h2>' . $ordering_html . '</h2>',
			'innerBlocks' => array(),
		);

		$result = $this->handler->translate( $block );

		$this->assertSame( $this->canonical_link_strong_em_tree(), $result['content'] );
	}
}
