<?php
/**
 * End-to-end tests for the core/list translator.
 *
 * @package Post_To_Convex
 */

declare( strict_types=1 );

namespace PostToConvex\Tests;

use PostToConvex\BlockHandlers\InlineTreeParser;
use PostToConvex\BlockHandlers\ListHandler;
use PostToConvex\Tests\Support\BlockHandlerTestSupport;
use WP_UnitTestCase;

/**
 * Drives the list handler through every variant in
 * `tests/data/sample-list-block-variants.html` so each block-attribute,
 * nested-structure, and inline-content concern has at least one explicit
 * assertion.
 */
class ListHandlerTest extends WP_UnitTestCase {

	use BlockHandlerTestSupport;

	/**
	 * Sample HTML fixture name.
	 *
	 * @var string
	 */
	private const SAMPLE_FILE = 'sample-list-block-variants.html';

	/**
	 * Cached flattened list blocks (filled lazily on first access).
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private static ?array $sample_blocks = null;

	/**
	 * List handler under test.
	 *
	 * @var ListHandler
	 */
	private ListHandler $handler;

	/**
	 * Build a fresh handler with a deterministic stub resolver before each test.
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

		$this->handler = new ListHandler( new InlineTreeParser(), $resolver );
	}

	/**
	 * Lazily load and flatten every top-level list block in the sample.
	 *
	 * @return array<int, array<string, mixed>> List blocks in document order.
	 */
	private function sample_blocks(): array {
		if ( null === self::$sample_blocks ) {
			self::$sample_blocks = $this->load_blocks_of_type( self::SAMPLE_FILE, 'core/list' );
		}

		return self::$sample_blocks;
	}

	/**
	 * Translate a list block by its index in the (flattened) sample.
	 *
	 * @param int $index Index into the flattened sample blocks.
	 * @return array<string, mixed> Translated block.
	 */
	private function translate_index( int $index ): array {
		$blocks = $this->sample_blocks();
		$this->assertArrayHasKey(
			$index,
			$blocks,
			sprintf( 'Sample HTML missing list block at index %d', $index )
		);

		return $this->handler->translate( $blocks[ $index ] );
	}

	/**
	 * Every translated block reports `core/list` as its blockName.
	 *
	 * @return void
	 */
	public function test_block_name_is_core_list(): void {
		$this->assertSame( 'core/list', $this->translate_index( 0 )['blockName'] );
	}

	/**
	 * `ordered` defaults to false when the attribute is absent.
	 *
	 * @return void
	 */
	public function test_ordered_defaults_to_false(): void {
		$this->assertFalse( $this->translate_index( 0 )['ordered'] );
	}

	/**
	 * `ordered` surfaces as true when `attrs.ordered` is the literal boolean true.
	 *
	 * @return void
	 */
	public function test_ordered_true(): void {
		$result = $this->translate_index( 2 );
		$this->assertTrue( $result['ordered'] );
		$this->assertFalse( $result['reversed'] );
		$this->assertNull( $result['start'] );
		$this->assertNull( $result['type'] );
	}

	/**
	 * `start` carries through on ordered lists.
	 *
	 * @return void
	 */
	public function test_start_value(): void {
		$this->assertSame( 4, $this->translate_index( 3 )['start'] );
	}

	/**
	 * An empty list-item emits an empty inline AST.
	 *
	 * @return void
	 */
	public function test_empty_list_item_content(): void {
		$items = $this->translate_index( 3 )['items'];
		$this->assertCount( 4, $items );
		$this->assertSame( array(), $items[3]['content'] );
		$this->assertNull( $items[3]['nested'] );
	}

	/**
	 * Ordered list `type` values carry through for each number-style variant.
	 *
	 * @return void
	 */
	public function test_list_style_types(): void {
		$this->assertSame( 'upper-alpha', $this->translate_index( 4 )['type'] );
		$this->assertSame( 'lower-alpha', $this->translate_index( 5 )['type'] );
		$this->assertSame( 'upper-roman', $this->translate_index( 6 )['type'] );
		$this->assertSame( 'lower-roman', $this->translate_index( 7 )['type'] );
	}

	/**
	 * `reversed` surfaces as true when the attribute is set.
	 *
	 * @return void
	 */
	public function test_reversed_order(): void {
		$this->assertTrue( $this->translate_index( 8 )['reversed'] );
	}

	/**
	 * The plain unordered sample nests lists three levels deep on the middle item.
	 *
	 * @return void
	 */
	public function test_nested_three_level_structure(): void {
		$items = $this->translate_index( 0 )['items'];

		$this->assertCount( 3, $items );
		$this->assertNull( $items[0]['nested'] );
		$this->assertNull( $items[2]['nested'] );

		$level1 = $items[1]['nested'];
		$this->assertIsArray( $level1 );
		$this->assertFalse( $level1['ordered'] );
		$this->assertCount( 1, $level1['items'] );

		$level2 = $level1['items'][0]['nested'];
		$this->assertIsArray( $level2 );
		$this->assertCount( 1, $level2['items'] );
		$this->assertNull( $level2['items'][0]['nested'] );

		$this->assertSame(
			array(
				array(
					'type' => 'text',
					'text' => 'Another nested list item',
				),
			),
			$level2['items'][0]['content']
		);
	}

	/**
	 * Bold / italic / link / mark variants parse into the canonical AST shape
	 * on the appropriate list items.
	 *
	 * @return void
	 */
	public function test_inline_formatting_in_items(): void {
		$items = $this->translate_index( 1 )['items'];

		$this->assertSame(
			array(
				array(
					'type'     => 'strong',
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'List item',
						),
					),
				),
			),
			$items[0]['content']
		);

		$this->assertSame(
			array(
				array(
					'type'     => 'em',
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'List item',
						),
					),
				),
			),
			$items[1]['content']
		);

		$nested_link = $items[1]['nested']['items'][0]['content'];
		$this->assertSame(
			array(
				array(
					'type'     => 'link',
					'attrs'    => array( 'href' => '/' ),
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'Nested list item',
						),
					),
				),
			),
			$nested_link
		);

		$deepest = $items[1]['nested']['items'][0]['nested']['items'][0]['content'];
		$this->assertCount( 1, $deepest );
		$this->assertSame( 'mark', $deepest[0]['type'] );
		$this->assertTrue( $deepest[0]['attrs']['hasInlineColor'] );
		$this->assertSame(
			array(
				'backgroundColor' => '#aff6b4',
				'color'           => '#e30000',
			),
			$deepest[0]['attrs']['style']
		);
		$this->assertSame(
			array(
				array(
					'type' => 'text',
					'text' => 'Another nested list item',
				),
			),
			$deepest[0]['children']
		);
	}

	/**
	 * `textColor` only — text preset resolves; background stays null; link
	 * tracks text color.
	 *
	 * @return void
	 */
	public function test_color_text_only(): void {
		$colors = $this->translate_index( 9 )['colors'];

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
		$colors = $this->translate_index( 10 )['colors'];

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
		$colors = $this->translate_index( 11 )['colors'];

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
			$this->translate_index( 12 )['typography']['fontSize']
		);
		$this->assertSame(
			array(
				'token'    => 'medium',
				'resolved' => '20px',
			),
			$this->translate_index( 13 )['typography']['fontSize']
		);
		$this->assertSame(
			array(
				'token'    => 'large',
				'resolved' => '36px',
			),
			$this->translate_index( 14 )['typography']['fontSize']
		);
		$this->assertSame(
			array(
				'token'    => 'x-large',
				'resolved' => '42px',
			),
			$this->translate_index( 15 )['typography']['fontSize']
		);
	}

	/**
	 * Custom typography (font-style + font-weight) carries through.
	 *
	 * @return void
	 */
	public function test_typography_font_style_and_weight(): void {
		$light = $this->translate_index( 16 )['typography'];
		$this->assertSame( 'italic', $light['fontStyle'] );
		$this->assertSame( '200', $light['fontWeight'] );

		$black = $this->translate_index( 17 )['typography'];
		$this->assertSame( 'italic', $black['fontStyle'] );
		$this->assertSame( '900', $black['fontWeight'] );
	}

	/**
	 * Line-height and letter-spacing carry through as literal CSS values.
	 *
	 * @return void
	 */
	public function test_typography_line_height_and_letter_spacing(): void {
		$this->assertSame( '2.4', $this->translate_index( 18 )['typography']['lineHeight'] );
		$this->assertSame( '7px', $this->translate_index( 19 )['typography']['letterSpacing'] );
	}

	/**
	 * Underline and strikethrough text-decoration values carry through.
	 *
	 * @return void
	 */
	public function test_typography_text_decoration(): void {
		$this->assertSame(
			'underline',
			$this->translate_index( 20 )['typography']['textDecoration']
		);
		$this->assertSame(
			'line-through',
			$this->translate_index( 21 )['typography']['textDecoration']
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
			$this->translate_index( 22 )['typography']['textTransform']
		);
		$this->assertSame(
			'lowercase',
			$this->translate_index( 23 )['typography']['textTransform']
		);
		$this->assertSame(
			'capitalize',
			$this->translate_index( 24 )['typography']['textTransform']
		);
	}

	/**
	 * Spacing presets resolve through the spacing palette and emit
	 * `{ token, resolved }` for every side.
	 *
	 * @return void
	 */
	public function test_spacing_padding_preset(): void {
		$padding = $this->translate_index( 25 )['spacing']['padding'];

		$expected_side = array(
			'token'    => '50',
			'resolved' => '1.25rem',
		);

		$this->assertSame( $expected_side, $padding['top'] );
		$this->assertSame( $expected_side, $padding['right'] );
		$this->assertSame( $expected_side, $padding['bottom'] );
		$this->assertSame( $expected_side, $padding['left'] );

		$this->assertNull( $this->translate_index( 25 )['spacing']['margin'] );
	}

	/**
	 * Margin presets resolve identically to padding presets.
	 *
	 * @return void
	 */
	public function test_spacing_margin_preset(): void {
		$margin = $this->translate_index( 26 )['spacing']['margin'];

		$expected_side = array(
			'token'    => '50',
			'resolved' => '1.25rem',
		);

		$this->assertSame( $expected_side, $margin['top'] );
		$this->assertSame( $expected_side, $margin['right'] );
		$this->assertSame( $expected_side, $margin['bottom'] );
		$this->assertSame( $expected_side, $margin['left'] );

		$this->assertNull( $this->translate_index( 26 )['spacing']['padding'] );
	}

	/**
	 * All six valid orderings of a "bold italic linked" inline run.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function provide_link_strong_em_orderings(): array {
		return array(
			'a > strong > em' => array( '<a href="/x"><strong><em>x</em></strong></a>' ),
			'a > em > strong' => array( '<a href="/x"><em><strong>x</strong></em></a>' ),
			'strong > a > em' => array( '<strong><a href="/x"><em>x</em></a></strong>' ),
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
	 * run collapses to the canonical AST on the first list item's content.
	 *
	 * @dataProvider provide_link_strong_em_orderings
	 *
	 * @param string $ordering_html Inline HTML for one of the six valid orderings.
	 * @return void
	 */
	public function test_inline_content_canonicalization_collapses_all_orderings( string $ordering_html ): void {
		$block = array(
			'blockName'   => 'core/list',
			'attrs'       => array(),
			'innerHTML'   => '<ul></ul>',
			'innerBlocks' => array(
				array(
					'blockName'    => 'core/list-item',
					'attrs'        => array(),
					'innerHTML'    => '<li>' . $ordering_html . '</li>',
					'innerContent' => array( '<li>' . $ordering_html . '</li>' ),
					'innerBlocks'  => array(),
				),
			),
		);

		$result = $this->handler->translate( $block );

		$this->assertSame( $this->canonical_link_strong_em_tree(), $result['items'][0]['content'] );
	}
}
