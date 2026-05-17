<?php
/**
 * Tests for the block translator registry and the Util::translate_blocks wrapper.
 *
 * @package Post_To_Convex
 */

declare( strict_types=1 );

namespace PostToConvex\Tests;

use PostToConvex\BlockHandlers\BlockHandlerInterface;
use WP_UnitTestCase;
use PostToConvex\BlockHandlers\BlockTranslator;
use PostToConvex\Util;

/**
 * Verifies the registry's dispatch behaviour, the recursion into innerBlocks
 * for unhandled wrapper blocks, and that `Util::translate_blocks` returns
 * valid JSON whose decoded form matches the translator output.
 */
class BlockTranslatorTest extends WP_UnitTestCase {

	/**
	 * A handler that records every block it sees and emits a marker payload
	 * so tests can assert which block was dispatched to it.
	 */
	private function make_recording_handler(): BlockHandlerInterface {
		return new class() implements BlockHandlerInterface {

			/**
			 * Captured block names, in dispatch order.
			 *
			 * @var array<int, string>
			 */
			public array $seen = array();

			/**
			 * Translate a block by recording its name and emitting a marker.
			 *
			 * @param array<string, mixed> $block A block from parse_blocks().
			 * @return array<string, mixed> Marker payload.
			 */
			public function translate( array $block ): array {
				$name         = is_string( $block['blockName'] ?? null ) ? $block['blockName'] : '';
				$this->seen[] = $name;

				return array(
					'blockName' => $name,
					'marker'    => true,
				);
			}
		};
	}

	/**
	 * Unknown blocks at the top level are skipped (no entries in the result).
	 *
	 * @return void
	 */
	public function test_unknown_blocks_are_skipped(): void {
		$translator = new BlockTranslator();

		$result = $translator->translate(
			array(
				array(
					'blockName'   => 'core/paragraph',
					'innerBlocks' => array(),
				),
				array(
					'blockName'   => 'core/list',
					'innerBlocks' => array(),
				),
			)
		);

		$this->assertSame( array(), $result );
	}

	/**
	 * Registered handlers are dispatched, in document order.
	 *
	 * @return void
	 */
	public function test_registered_handler_dispatches(): void {
		$handler    = $this->make_recording_handler();
		$translator = new BlockTranslator();
		$translator->register( 'core/heading', $handler );

		$result = $translator->translate(
			array(
				array(
					'blockName'   => 'core/heading',
					'innerBlocks' => array(),
				),
				array(
					'blockName'   => 'core/paragraph',
					'innerBlocks' => array(),
				),
				array(
					'blockName'   => 'core/heading',
					'innerBlocks' => array(),
				),
			)
		);

		$this->assertSame( array( 'core/heading', 'core/heading' ), $handler->seen );
		$this->assertCount( 2, $result );
		$this->assertTrue( $result[0]['marker'] );
		$this->assertTrue( $result[1]['marker'] );
	}

	/**
	 * Unknown wrapper blocks are skipped but their innerBlocks are recursed
	 * into so handled descendants still surface.
	 *
	 * @return void
	 */
	public function test_inner_blocks_are_recursed(): void {
		$handler    = $this->make_recording_handler();
		$translator = new BlockTranslator();
		$translator->register( 'core/heading', $handler );

		$result = $translator->translate(
			array(
				array(
					'blockName'   => 'core/group',
					'innerBlocks' => array(
						array(
							'blockName'   => 'core/heading',
							'innerBlocks' => array(),
						),
						array(
							'blockName'   => 'core/columns',
							'innerBlocks' => array(
								array(
									'blockName'   => 'core/heading',
									'innerBlocks' => array(),
								),
							),
						),
					),
				),
			)
		);

		$this->assertSame( array( 'core/heading', 'core/heading' ), $handler->seen );
		$this->assertCount( 2, $result );
	}

	/**
	 * The default registry handles `core/heading` out of the box.
	 *
	 * @return void
	 */
	public function test_with_defaults_registers_heading(): void {
		$translator = BlockTranslator::with_defaults();

		$result = $translator->translate(
			array(
				array(
					'blockName' => 'core/heading',
					'attrs'     => array( 'level' => 3 ),
					'innerHTML' => '<h3 class="wp-block-heading">Hello</h3>',
				),
			)
		);

		$this->assertCount( 1, $result );
		$this->assertSame( 'core/heading', $result[0]['blockName'] );
		$this->assertSame( 3, $result[0]['level'] );
		$this->assertSame(
			array(
				array(
					'type' => 'text',
					'text' => 'Hello',
				),
			),
			$result[0]['content']
		);
	}

	/**
	 * `Util::translate_blocks` returns a JSON string whose decoded value is
	 * structurally equal to the translator's array output.
	 *
	 * @return void
	 */
	public function test_util_translate_blocks_returns_valid_json(): void {
		$content = "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">Hi</h3>\n<!-- /wp:heading -->";

		$encoded = Util::translate_blocks( $content );
		$decoded = json_decode( $encoded, true );

		$this->assertIsArray( $decoded );
		$this->assertCount( 1, $decoded );
		$this->assertSame( 'core/heading', $decoded[0]['blockName'] );
		$this->assertSame( 3, $decoded[0]['level'] );
		$this->assertSame(
			array(
				array(
					'type' => 'text',
					'text' => 'Hi',
				),
			),
			$decoded[0]['content']
		);
	}

	/**
	 * Empty (or no-block) input still returns valid JSON, namely `[]`.
	 *
	 * @return void
	 */
	public function test_util_translate_blocks_returns_empty_array_for_empty_content(): void {
		$this->assertSame( '[]', Util::translate_blocks( '' ) );
	}
}
