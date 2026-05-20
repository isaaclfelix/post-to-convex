<?php
/**
 * Translates core/paragraph blocks into the JSON schema consumed by the headless renderer.
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
 * Translates a parsed `core/paragraph` block into the JSON-ready array form.
 *
 * Output schema (each field that accepts presets uses { token, resolved } where
 * `token` is the theme.json slug and `resolved` is the concrete CSS value
 * looked up against the active theme — either may be null):
 *
 *   {
 *     blockName:  'core/paragraph',
 *     dropCap:    bool,
 *     textAlign:  'left' | 'center' | 'right' | null,
 *     colors:     { text, background, link },
 *     typography: { fontSize, fontStyle, fontWeight, lineHeight,
 *                   letterSpacing, textDecoration, textTransform, writingMode },
 *     spacing:    { padding: { top, right, bottom, left } | null,
 *                   margin:  { top, right, bottom, left } | null },
 *     content:    [ inline AST ]
 *   }
 *
 * Notes:
 *
 * - WordPress stores paragraph text alignment in `attrs.align` (with values
 *   `left | center | right`), producing the same `has-text-align-*` class
 *   that heading's `textAlign` does. To let consumers share alignment-rendering
 *   logic across block types, this handler emits the schema field as
 *   `textAlign`, matching {@see HeadingHandler}.
 * - Paragraphs do not support block-level `wide` / `full` alignment, so the
 *   schema deliberately omits an `align` field.
 */
class ParagraphHandler extends AbstractBlockHandler {

	/**
	 * Translate a single core/paragraph block.
	 *
	 * @param array<string, mixed> $block A block from parse_blocks().
	 * @return array<string, mixed> The translated block.
	 */
	public function translate( array $block ): array {
		$attrs      = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$style      = is_array( $attrs['style'] ?? null ) ? $attrs['style'] : array();
		$inner_html = is_string( $block['innerHTML'] ?? null ) ? $block['innerHTML'] : '';

		return array(
			'blockName'  => 'core/paragraph',
			'dropCap'    => $this->build_drop_cap( $attrs ),
			'textAlign'  => $this->nullable_string( $attrs['align'] ?? null ),
			'colors'     => $this->build_colors( $attrs, $style ),
			'typography' => $this->build_typography( $attrs, $style ),
			'spacing'    => $this->build_spacing( $style ),
			'content'    => $this->inline_parser->parse( $inner_html ),
		);
	}

	/**
	 * Resolve the dropCap boolean (defaults to false when absent).
	 *
	 * Only literal `true` enables the cap — any other value (including
	 * truthy strings or numbers) is treated as false to keep the contract
	 * narrow.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return bool Whether the paragraph has drop cap enabled.
	 */
	private function build_drop_cap( array $attrs ): bool {
		return true === ( $attrs['dropCap'] ?? false );
	}
}
