<?php
/**
 * Util functions.
 *
 * @package PostToConvex
 */

declare( strict_types=1 );

namespace PostToConvex;

use PostToConvex\BlockHandlers\BlockTranslator;

/**
 * Security check.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Utility functions.
 */
class Util {

	/**
	 * Translate Gutenberg blocks in a post's content into the JSON shape.
	 *
	 * Each registered block handler emits its own structured representation;
	 * unknown blocks are skipped (their `innerBlocks` are still recursed into,
	 * so handled descendants make it through wrapper blocks like core/group).
	 *
	 * @param string $content The raw post_content to translate.
	 * @return string A JSON-encoded array of translated blocks (`'[]'` when empty or on encode failure).
	 */
	public static function translate_blocks( string $content ): string {
		$blocks     = parse_blocks( $content );
		$translator = BlockTranslator::with_defaults();
		$translated = $translator->translate( $blocks );

		$encoded = wp_json_encode( $translated );

		return is_string( $encoded ) ? $encoded : '[]';
	}
}
