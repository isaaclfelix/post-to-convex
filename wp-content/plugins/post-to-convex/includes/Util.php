<?php
/**
 * Util functions.
 *
 * @package PostToConvex
 */

declare( strict_types=1 );

namespace PostToConvex;

/**
 * Security check.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Utility functions.
 */
class Util {
	/**
	 * Translate Gutenberg blocks in the content.
	 *
	 * @param string $content The content to translate.
	 * @return string The translated content.
	 */
	public static function translate_blocks( string $content ): string {
		$blocks = parse_blocks( $content );

		$translated_content = array();

		foreach ( $blocks as $block ) {
			if ( 'core/heading' === $block['blockName'] ) {
				$level        = intval( $block['attrs']['level'] );
				$html_content = $block['innerHTML'];

				$fragment = Dom::string_to_dom_fragment( $html_content );
				$content  = trim( Dom::get_text_content( $fragment ) );

				$translated_block = array(
					'blockName' => $block['blockName'],
					...compact( 'level', 'content' ),
				);

				$translated_content[] = $translated_block;
			}
		}

		return wp_json_encode( $translated_content );
	}
}
