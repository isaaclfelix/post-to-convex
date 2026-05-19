<?php
/**
 * End-to-end tests for the core/paragraph translator.
 *
 * @package Post_To_Convex
 */

declare( strict_types=1 );

namespace PostToConvex\Tests;

use PostToConvex\BlockHandlers\InlineTreeParser;
use PostToConvex\BlockHandlers\ParagraphHandler;
use PostToConvex\Tests\Support\BlockHandlerTestSupport;
use WP_UnitTestCase;

/**
 * Drives the paragraph handler through every variant in
 * `tests/data/sample-paragraph-block-variants.html` so each block-attribute
 * and inline-content concern has at least one explicit assertion.
 *
 * Tests are grouped by concern (dropCap / align / colors / typography /
 * spacing / inline content) so a regression in any one area produces a
 * localized failure.
 */
class ParagraphHandlerTest extends WP_UnitTestCase {

	use BlockHandlerTestSupport;

	/**
	 * Sample HTML fixture name.
	 *
	 * @var string
	 */
	private const SAMPLE_FILE = 'sample-paragraph-block-variants.html';

	/**
	 * Cached flattened paragraph blocks (filled lazily on first access).
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private static ?array $sample_blocks = null;

	/**
	 * Paragraph handler under test.
	 *
	 * @var ParagraphHandler
	 */
	private ParagraphHandler $handler;

	/**
	 * Build a fresh handler with a deterministic stub resolver before each
	 * test. The stub bypasses theme.json so paragraph-handler assertions stay
	 * focused on translator behaviour. Resolver behaviour against the real WP
	 * integration is covered separately in {@see PresetResolverTest}.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$resolver = $this->make_fake_resolver(
			array(
				'luminous-vivid-orange' => '#ff6900',
				'vivid-green-cyan'      => '#00d084',
				'pale-cyan-blue'        => '#abb8c3',
			),
			array(
				'small'   => '13px',
				'medium'  => '20px',
				'large'   => '36px',
				'x-large' => '42px',
			),
			array( '50' => '1.25rem' )
		);

		$this->handler = new ParagraphHandler( new InlineTreeParser(), $resolver );
	}

	/**
	 * Lazily load and flatten every paragraph block in the sample.
	 *
	 * @return array<int, array<string, mixed>> Paragraph blocks in document order.
	 */
	private function sample_blocks(): array {
		if ( null === self::$sample_blocks ) {
			self::$sample_blocks = $this->load_blocks_of_type( self::SAMPLE_FILE, 'core/paragraph' );
		}

		return self::$sample_blocks;
	}

	/**
	 * Translate a paragraph block by its index in the (flattened) sample.
	 *
	 * @param int $index Index into the flattened sample blocks.
	 * @return array<string, mixed> Translated block.
	 */
	private function translate_index( int $index ): array {
		$blocks = $this->sample_blocks();
		$this->assertArrayHasKey(
			$index,
			$blocks,
			sprintf( 'Sample HTML missing paragraph block at index %d', $index )
		);

		return $this->handler->translate( $blocks[ $index ] );
	}

	/**
	 * Every translated block reports `core/paragraph` as its blockName.
	 *
	 * @return void
	 */
	public function test_block_name_is_core_paragraph(): void {
		$this->assertSame( 'core/paragraph', $this->translate_index( 0 )['blockName'] );
	}

	/**
	 * `dropCap` defaults to false when the attribute is absent.
	 *
	 * @return void
	 */
	public function test_drop_cap_defaults_to_false(): void {
		$this->assertFalse( $this->translate_index( 0 )['dropCap'] );
	}

	/**
	 * `dropCap` surfaces as true when `attrs.dropCap` is the literal boolean
	 * `true`.
	 *
	 * @return void
	 */
	public function test_drop_cap_true_when_attribute_set(): void {
		$this->assertTrue( $this->translate_index( 1 )['dropCap'] );
	}

	/**
	 * The dropCap paragraph in the sample mixes a plain prefix, a `<strong>`
	 * span, and a trailing punctuation text node — exercising the canonical
	 * "text / strong / text" shape end-to-end.
	 *
	 * Whitespace inside the `<p>` (indentation between the opening tag and
	 * the first text leaf) is preserved verbatim by design: only leaves that
	 * sit at the very outer boundary of the parsed fragment are trimmed.
	 *
	 * @return void
	 */
	public function test_drop_cap_paragraph_inline_content_is_parsed(): void {
		$content = $this->translate_index( 1 )['content'];

		$this->assertCount( 3, $content );

		$this->assertSame( 'text', $content[0]['type'] );
		$this->assertStringContainsString( 'A paragraph with drop cap enabled.', $content[0]['text'] );

		$this->assertSame( 'strong', $content[1]['type'] );
		$this->assertSame(
			array(
				array(
					'type' => 'text',
					'text' => 'Note: There is a spacer block below, to clear the next block',
				),
			),
			$content[1]['children']
		);

		$this->assertSame( 'text', $content[2]['type'] );
		$this->assertStringContainsString( '.', $content[2]['text'] );
	}

	/**
	 * Bold / italic / link / mark variants parse into the canonical AST shape,
	 * mirroring `HeadingHandlerTest::test_inline_bold_italic_link_mark`.
	 *
	 * @return void
	 */
	public function test_inline_bold_italic_link_mark(): void {
		$this->assertSame(
			array(
				array(
					'type'     => 'strong',
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'A bold paragraph.',
						),
					),
				),
			),
			$this->translate_index( 2 )['content']
		);

		$this->assertSame(
			array(
				array(
					'type'     => 'em',
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'An italic paragraph.',
						),
					),
				),
			),
			$this->translate_index( 3 )['content']
		);

		$this->assertSame(
			array(
				array(
					'type'     => 'link',
					'attrs'    => array( 'href' => '/' ),
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'A linked paragraph.',
						),
					),
				),
			),
			$this->translate_index( 4 )['content']
		);

		$mark_content = $this->translate_index( 5 )['content'];
		$this->assertCount( 1, $mark_content );
		$this->assertSame( 'mark', $mark_content[0]['type'] );
		$this->assertTrue( $mark_content[0]['attrs']['hasInlineColor'] );
		$this->assertSame(
			array(
				'backgroundColor' => '#2802f1',
				'color'           => '#f9ff00',
			),
			$mark_content[0]['attrs']['style']
		);
		$this->assertSame(
			array(
				array(
					'type' => 'text',
					'text' => 'A highlighted paragraph.',
				),
			),
			$mark_content[0]['children']
		);
	}

	/**
	 * A paragraph with `textColor` but no link override resolves the text
	 * color and surfaces the matching link color through the
	 * `style.elements.link.color.text` token.
	 *
	 * @return void
	 */
	public function test_color_text_only(): void {
		$colors = $this->translate_index( 6 )['colors'];

		$this->assertSame(
			array(
				'token'    => 'luminous-vivid-orange',
				'resolved' => '#ff6900',
			),
			$colors['text']
		);
		$this->assertNull( $colors['background'] );
		$this->assertSame(
			array(
				'token'    => 'luminous-vivid-orange',
				'resolved' => '#ff6900',
			),
			$colors['link']
		);
	}

	/**
	 * The link color preset can diverge from the text color preset — both
	 * resolve independently.
	 *
	 * @return void
	 */
	public function test_color_text_with_link_override(): void {
		$colors = $this->translate_index( 7 )['colors'];

		$this->assertSame(
			array(
				'token'    => 'luminous-vivid-orange',
				'resolved' => '#ff6900',
			),
			$colors['text']
		);
		$this->assertNull( $colors['background'] );
		$this->assertSame(
			array(
				'token'    => 'vivid-green-cyan',
				'resolved' => '#00d084',
			),
			$colors['link']
		);
	}

	/**
	 * `textColor` and `backgroundColor` both resolve through the palette;
	 * the link color tracks the text color in this variant.
	 *
	 * @return void
	 */
	public function test_color_text_and_background(): void {
		$colors = $this->translate_index( 8 )['colors'];

		$this->assertSame(
			array(
				'token'    => 'luminous-vivid-orange',
				'resolved' => '#ff6900',
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
				'token'    => 'luminous-vivid-orange',
				'resolved' => '#ff6900',
			),
			$colors['link']
		);
	}

	/**
	 * Independent text / background / link colors all resolve.
	 *
	 * @return void
	 */
	public function test_color_text_background_and_link_override(): void {
		$colors = $this->translate_index( 9 )['colors'];

		$this->assertSame(
			array(
				'token'    => 'luminous-vivid-orange',
				'resolved' => '#ff6900',
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
				'token'    => 'vivid-green-cyan',
				'resolved' => '#00d084',
			),
			$colors['link']
		);
	}

	/**
	 * A paragraph with no alignment attribute resolves `textAlign` to null.
	 *
	 * @return void
	 */
	public function test_text_align_null_when_missing(): void {
		$this->assertNull( $this->translate_index( 10 )['textAlign'] );
	}

	/**
	 * Paragraph's `attrs.align` (`left|center|right`) surfaces as schema
	 * `textAlign` — matching the heading handler's text-alignment field.
	 *
	 * @return void
	 */
	public function test_text_align_left_center_right(): void {
		$this->assertSame( 'left', $this->translate_index( 11 )['textAlign'] );
		$this->assertSame( 'center', $this->translate_index( 12 )['textAlign'] );
		$this->assertSame( 'right', $this->translate_index( 13 )['textAlign'] );
	}

	/**
	 * The two vertical paragraphs nested inside the trailing flex `core/group`
	 * preserve both their text alignment and `writingMode`.
	 *
	 * @return void
	 */
	public function test_writing_mode_vertical_rl(): void {
		$blocks = $this->sample_blocks();
		$this->assertGreaterThanOrEqual(
			16,
			count( $blocks ),
			'Sample is expected to include the two vertical paragraphs nested in core/group.'
		);

		$left  = $this->handler->translate( $blocks[14] );
		$right = $this->handler->translate( $blocks[15] );

		$this->assertSame( 'left', $left['textAlign'] );
		$this->assertSame( 'vertical-rl', $left['typography']['writingMode'] );

		$this->assertSame( 'right', $right['textAlign'] );
		$this->assertSame( 'vertical-rl', $right['typography']['writingMode'] );
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
			$this->translate_index( 16 )['typography']['fontSize']
		);
		$this->assertSame(
			array(
				'token'    => 'medium',
				'resolved' => '20px',
			),
			$this->translate_index( 17 )['typography']['fontSize']
		);
		$this->assertSame(
			array(
				'token'    => 'large',
				'resolved' => '36px',
			),
			$this->translate_index( 18 )['typography']['fontSize']
		);
		$this->assertSame(
			array(
				'token'    => 'x-large',
				'resolved' => '42px',
			),
			$this->translate_index( 19 )['typography']['fontSize']
		);
	}

	/**
	 * Custom typography (font-style + font-weight) carries through.
	 *
	 * @return void
	 */
	public function test_typography_font_style_and_weight(): void {
		$light = $this->translate_index( 20 )['typography'];
		$this->assertSame( 'italic', $light['fontStyle'] );
		$this->assertSame( '200', $light['fontWeight'] );

		$black = $this->translate_index( 21 )['typography'];
		$this->assertSame( 'italic', $black['fontStyle'] );
		$this->assertSame( '900', $black['fontWeight'] );
	}

	/**
	 * Line-height and letter-spacing carry through as literal CSS values.
	 *
	 * @return void
	 */
	public function test_typography_line_height_and_letter_spacing(): void {
		$this->assertSame( '2.4', $this->translate_index( 22 )['typography']['lineHeight'] );
		$this->assertSame( '7px', $this->translate_index( 23 )['typography']['letterSpacing'] );
	}

	/**
	 * Underline and strikethrough text-decoration values carry through.
	 *
	 * @return void
	 */
	public function test_typography_text_decoration(): void {
		$this->assertSame(
			'underline',
			$this->translate_index( 24 )['typography']['textDecoration']
		);
		$this->assertSame(
			'line-through',
			$this->translate_index( 25 )['typography']['textDecoration']
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
			$this->translate_index( 26 )['typography']['textTransform']
		);
		$this->assertSame(
			'lowercase',
			$this->translate_index( 27 )['typography']['textTransform']
		);
		$this->assertSame(
			'capitalize',
			$this->translate_index( 28 )['typography']['textTransform']
		);
	}

	/**
	 * Spacing presets resolve through the spacing palette and emit
	 * `{ token, resolved }` for every side.
	 *
	 * @return void
	 */
	public function test_spacing_padding_preset(): void {
		$padding = $this->translate_index( 29 )['spacing']['padding'];

		$expected_side = array(
			'token'    => '50',
			'resolved' => '1.25rem',
		);

		$this->assertSame( $expected_side, $padding['top'] );
		$this->assertSame( $expected_side, $padding['right'] );
		$this->assertSame( $expected_side, $padding['bottom'] );
		$this->assertSame( $expected_side, $padding['left'] );

		$this->assertNull( $this->translate_index( 29 )['spacing']['margin'] );
	}

	/**
	 * Margin presets resolve identically to padding presets.
	 *
	 * @return void
	 */
	public function test_spacing_margin_preset(): void {
		$margin = $this->translate_index( 30 )['spacing']['margin'];

		$expected_side = array(
			'token'    => '50',
			'resolved' => '1.25rem',
		);

		$this->assertSame( $expected_side, $margin['top'] );
		$this->assertSame( $expected_side, $margin['right'] );
		$this->assertSame( $expected_side, $margin['bottom'] );
		$this->assertSame( $expected_side, $margin['left'] );

		$this->assertNull( $this->translate_index( 30 )['spacing']['padding'] );
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
	 * `core/paragraph` block and {@see ParagraphHandler::translate()},
	 * collapses to the canonical `link > strong > em > text` AST.
	 *
	 * The parser-level guarantee is exercised in
	 * {@see InlineTreeParserTest::test_canonicalization_collapses_all_orderings}.
	 * This test confirms the canonicalization survives the handler-level
	 * wiring for paragraphs specifically.
	 *
	 * @dataProvider provide_link_strong_em_orderings
	 *
	 * @param string $ordering_html Inline HTML for one of the six valid orderings.
	 * @return void
	 */
	public function test_inline_content_canonicalization_collapses_all_orderings( string $ordering_html ): void {
		$block = array(
			'blockName'   => 'core/paragraph',
			'attrs'       => array(),
			'innerHTML'   => '<p>' . $ordering_html . '</p>',
			'innerBlocks' => array(),
		);

		$result = $this->handler->translate( $block );

		$this->assertSame( $this->canonical_link_strong_em_tree(), $result['content'] );
	}
}
