<?php
/**
 * Block handler contract.
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
 * Translates a parsed Gutenberg block into a JSON-ready associative array.
 */
interface BlockHandlerInterface {

	/**
	 * Translate a single parsed block.
	 *
	 * @param array<string, mixed> $block A block from parse_blocks().
	 * @return array<string, mixed> The translated block.
	 */
	public function translate( array $block ): array;
}
