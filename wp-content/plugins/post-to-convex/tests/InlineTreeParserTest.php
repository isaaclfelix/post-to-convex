<?php
/**
 * Tests for the inline tree parser.
 *
 * @package Post_To_Convex
 */

declare( strict_types=1 );

namespace PostToConvex\Tests;

use PostToConvex\BlockHandlers\InlineTreeParser;
use WP_UnitTestCase;

/**
 * Covers basic node shapes, whitespace handling, unknown-tag flattening, and
 * the canonicalization rules that collapse arbitrary authoring orders into a
 * single deterministic AST.
 */
class InlineTreeParserTest extends WP_UnitTestCase {

	/**
	 * Parser under test.
	 *
	 * @var InlineTreeParser
	 */
	private InlineTreeParser $parser;

	/**
	 * Build a fresh parser before each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->parser = new InlineTreeParser();
	}

	/**
	 * Plain text with no inline elements becomes a single text node.
	 *
	 * @return void
	 */
	public function test_plain_text(): void {
		$ast = $this->parser->parse( 'Hello world' );

		$this->assertSame(
			array(
				array(
					'type' => 'text',
					'text' => 'Hello world',
				),
			),
			$ast
		);
	}

	/**
	 * An empty (or whitespace-only) input produces an empty AST.
	 *
	 * @return void
	 */
	public function test_empty_input_produces_empty_ast(): void {
		$this->assertSame( array(), $this->parser->parse( '' ) );
		$this->assertSame( array(), $this->parser->parse( "   \n\t" ) );
	}

	/**
	 * Bold and italic alias tags (<b>/<i>) normalize to <strong>/<em>.
	 *
	 * @return void
	 */
	public function test_bold_italic_and_aliases(): void {
		$strong = $this->parser->parse( '<strong>x</strong>' );
		$bold   = $this->parser->parse( '<b>x</b>' );
		$em     = $this->parser->parse( '<em>x</em>' );
		$italic = $this->parser->parse( '<i>x</i>' );

		$expected_strong = array(
			array(
				'type'     => 'strong',
				'children' => array(
					array(
						'type' => 'text',
						'text' => 'x',
					),
				),
			),
		);
		$expected_em     = array(
			array(
				'type'     => 'em',
				'children' => array(
					array(
						'type' => 'text',
						'text' => 'x',
					),
				),
			),
		);

		$this->assertSame( $expected_strong, $strong );
		$this->assertSame( $expected_strong, $bold );
		$this->assertSame( $expected_em, $em );
		$this->assertSame( $expected_em, $italic );
	}

	/**
	 * Sibling text + inline element + text yields three nodes in document order.
	 *
	 * @return void
	 */
	public function test_mixed_siblings_preserve_order(): void {
		$ast = $this->parser->parse( 'pre <strong>bold</strong> post' );

		$this->assertSame(
			array(
				array(
					'type' => 'text',
					'text' => 'pre ',
				),
				array(
					'type'     => 'strong',
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'bold',
						),
					),
				),
				array(
					'type' => 'text',
					'text' => ' post',
				),
			),
			$ast
		);
	}

	/**
	 * Anchors capture href, target, and rel; empty optional attrs are omitted.
	 *
	 * @return void
	 */
	public function test_link_attrs(): void {
		$ast = $this->parser->parse( '<a href="/home">home</a>' );

		$this->assertSame(
			array(
				array(
					'type'     => 'link',
					'attrs'    => array( 'href' => '/home' ),
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'home',
						),
					),
				),
			),
			$ast
		);

		$ast_with_target = $this->parser->parse(
			'<a href="https://example.com" target="_blank" rel="noopener">ext</a>'
		);

		$this->assertSame(
			array(
				array(
					'type'     => 'link',
					'attrs'    => array(
						'href'   => 'https://example.com',
						'target' => '_blank',
						'rel'    => 'noopener',
					),
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'ext',
						),
					),
				),
			),
			$ast_with_target
		);
	}

	/**
	 * A `<mark>` with a style attribute and the `has-inline-color` class
	 * exposes both pieces of metadata under `attrs`.
	 *
	 * @return void
	 */
	public function test_mark_style_and_class(): void {
		$ast = $this->parser->parse(
			'<mark style="background-color: #6e00ff; color: #06eff7" class="has-inline-color">x</mark>'
		);

		$this->assertSame(
			array(
				array(
					'type'     => 'mark',
					'attrs'    => array(
						'hasInlineColor' => true,
						'style'          => array(
							'backgroundColor' => '#6e00ff',
							'color'           => '#06eff7',
						),
					),
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'x',
						),
					),
				),
			),
			$ast
		);
	}

	/**
	 * Pure-whitespace text nodes that frame an inline child are dropped.
	 *
	 * @return void
	 */
	public function test_outer_whitespace_is_trimmed(): void {
		$ast = $this->parser->parse( "\n\t<mark>x</mark>\n" );

		$this->assertSame(
			array(
				array(
					'type'     => 'mark',
					'attrs'    => array( 'hasInlineColor' => false ),
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'x',
						),
					),
				),
			),
			$ast
		);
	}

	/**
	 * An unknown inline element flattens — its children take the wrapper's place.
	 *
	 * @return void
	 */
	public function test_unknown_tag_flattens(): void {
		$ast = $this->parser->parse( 'pre <span>middle</span> post' );

		$this->assertSame(
			array(
				array(
					'type' => 'text',
					'text' => 'pre ',
				),
				array(
					'type' => 'text',
					'text' => 'middle',
				),
				array(
					'type' => 'text',
					'text' => ' post',
				),
			),
			$ast
		);
	}

	/**
	 * The canonical AST for "bold italic linked text". The parser should
	 * collapse every authoring order onto this single tree.
	 *
	 * @return array<string, mixed> Canonical AST.
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
	 * All six valid orderings of a triple-marked inline run.
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
	 * Every authoring order of a "bold italic linked" run produces the same
	 * canonical AST: link > strong > em > text.
	 *
	 * @dataProvider provide_link_strong_em_orderings
	 *
	 * @param string $html The source HTML for one of the six valid orderings.
	 * @return void
	 */
	public function test_canonicalization_collapses_all_orderings( string $html ): void {
		$ast = $this->parser->parse( $html );

		$this->assertSame( $this->canonical_link_strong_em_tree(), $ast );
	}

	/**
	 * `<strong>a<em>b</em>c</strong>` shares a single `strong` wrapper across
	 * all three leaves and emits `em` only around `b`.
	 *
	 * @return void
	 */
	public function test_canonicalization_partial_overlap_shares_outer_wrapper(): void {
		$ast = $this->parser->parse( '<strong>a<em>b</em>c</strong>' );

		$this->assertSame(
			array(
				array(
					'type'     => 'strong',
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'a',
						),
						array(
							'type'     => 'em',
							'children' => array(
								array(
									'type' => 'text',
									'text' => 'b',
								),
							),
						),
						array(
							'type' => 'text',
							'text' => 'c',
						),
					),
				),
			),
			$ast
		);
	}

	/**
	 * Two `<strong>` runs separated by an unmarked text leaf stay as two
	 * sibling `strong` wrappers — they are not merged across the gap.
	 *
	 * @return void
	 */
	public function test_canonicalization_non_contiguous_runs_are_not_merged(): void {
		$ast = $this->parser->parse( '<strong>a</strong>b<strong>c</strong>' );

		$this->assertSame(
			array(
				array(
					'type'     => 'strong',
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'a',
						),
					),
				),
				array(
					'type' => 'text',
					'text' => 'b',
				),
				array(
					'type'     => 'strong',
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'c',
						),
					),
				),
			),
			$ast
		);
	}

	/**
	 * Two adjacent links with the same href merge into one wrapper; differing
	 * hrefs stay as two siblings.
	 *
	 * @return void
	 */
	public function test_canonicalization_link_attrs_gate_merging(): void {
		$same = $this->parser->parse( '<a href="/x">a</a><a href="/x">b</a>' );

		$this->assertSame(
			array(
				array(
					'type'     => 'link',
					'attrs'    => array( 'href' => '/x' ),
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'a',
						),
						array(
							'type' => 'text',
							'text' => 'b',
						),
					),
				),
			),
			$same
		);

		$different = $this->parser->parse( '<a href="/x">a</a><a href="/y">b</a>' );

		$this->assertSame(
			array(
				array(
					'type'     => 'link',
					'attrs'    => array( 'href' => '/x' ),
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'a',
						),
					),
				),
				array(
					'type'     => 'link',
					'attrs'    => array( 'href' => '/y' ),
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'b',
						),
					),
				),
			),
			$different
		);
	}

	/**
	 * Two adjacent `<mark>` runs with the same style merge into one wrapper;
	 * differing styles stay as two siblings.
	 *
	 * @return void
	 */
	public function test_canonicalization_mark_attrs_gate_merging(): void {
		$same = $this->parser->parse(
			'<mark style="color: red">a</mark><mark style="color: red">b</mark>'
		);

		$this->assertSame(
			array(
				array(
					'type'     => 'mark',
					'attrs'    => array(
						'hasInlineColor' => false,
						'style'          => array( 'color' => 'red' ),
					),
					'children' => array(
						array(
							'type' => 'text',
							'text' => 'a',
						),
						array(
							'type' => 'text',
							'text' => 'b',
						),
					),
				),
			),
			$same
		);

		$different = $this->parser->parse(
			'<mark style="color: red">a</mark><mark style="color: blue">b</mark>'
		);

		$this->assertCount( 2, $different );
		$this->assertSame( 'mark', $different[0]['type'] );
		$this->assertSame( 'mark', $different[1]['type'] );
		$this->assertSame( array( 'color' => 'red' ), $different[0]['attrs']['style'] );
		$this->assertSame( array( 'color' => 'blue' ), $different[1]['attrs']['style'] );
	}
}
