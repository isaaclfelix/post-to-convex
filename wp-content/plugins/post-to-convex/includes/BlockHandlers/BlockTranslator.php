<?php
/**
 * Block translator registry.
 *
 * @package PostToConvex
 */

declare( strict_types=1 );

namespace PostToConvex\BlockHandlers;

/**
 * Security check.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Maps Gutenberg block names to BlockHandlerInterface instances and dispatches
 * a parsed block array to the matching handler.
 *
 * Unknown / unhandled blocks are skipped, but their innerBlocks are recursed
 * into so wrapper blocks (e.g. core/group) don't have to implement nested-block
 * logic explicitly — any descendant block with a registered handler still
 * makes it into the translated output.
 */
class BlockTranslator {

	/**
	 * Registered handlers, keyed by block name.
	 *
	 * @var array<string, BlockHandlerInterface>
	 */
	private array $handlers = array();

	/**
	 * Build a translator with the default set of handlers already registered.
	 *
	 * @return self A translator pre-populated with all built-in handlers.
	 */
	public static function with_defaults(): self {
		$instance = new self();
		$instance->register(
			'core/heading',
			new HeadingHandler( new InlineTreeParser(), new PresetResolver() )
		);
		$instance->register(
			'core/paragraph',
			new ParagraphHandler( new InlineTreeParser(), new PresetResolver() )
		);
		$instance->register(
			'core/list',
			new ListHandler( new InlineTreeParser(), new PresetResolver() )
		);

		return $instance;
	}

	/**
	 * Register (or replace) the handler for a given block name.
	 *
	 * @param string                $block_name The block name (e.g. 'core/heading').
	 * @param BlockHandlerInterface $handler    The handler instance.
	 * @return void
	 */
	public function register( string $block_name, BlockHandlerInterface $handler ): void {
		$this->handlers[ $block_name ] = $handler;
	}

	/**
	 * Translate a list of parsed blocks into the JSON-ready array form.
	 *
	 * @param array<int, array<string, mixed>> $blocks The blocks from parse_blocks().
	 * @return array<int, array<string, mixed>> Translated blocks (in document order).
	 */
	public function translate( array $blocks ): array {
		$result = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = $block['blockName'] ?? null;

			if ( is_string( $name ) && isset( $this->handlers[ $name ] ) ) {
				$result[] = $this->handlers[ $name ]->translate( $block );
				continue;
			}

			$inner = $block['innerBlocks'] ?? null;
			if ( is_array( $inner ) && ! empty( $inner ) ) {
				foreach ( $this->translate( $inner ) as $translated ) {
					$result[] = $translated;
				}
			}
		}

		return $result;
	}
}
