<?php
/**
 * Translates core/list blocks into the JSON schema consumed by the headless renderer.
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
 * Translates a parsed `core/list` block into the JSON-ready array form.
 *
 * Output schema (each field that accepts presets uses { token, resolved } where
 * `token` is the theme.json slug and `resolved` is the concrete CSS value
 * looked up against the active theme — either may be null):
 *
 *   {
 *     blockName:  'core/list',
 *     ordered:    bool,
 *     reversed:   bool,
 *     start:      int | null,
 *     type:       string | null,
 *     colors:     { text, background, link },
 *     typography: { fontSize, fontStyle, fontWeight, lineHeight,
 *                   letterSpacing, textDecoration, textTransform, writingMode },
 *     spacing:    { padding: { top, right, bottom, left } | null,
 *                   margin:  { top, right, bottom, left } | null },
 *     items:      [ { content: [ inline AST ], nested: ListTree | null }, ... ]
 *   }
 *
 * Lists are container blocks: item text lives on `core/list-item` children,
 * not on the list wrapper's innerHTML. Nested lists inside a list-item are
 * embedded under `items[].nested` rather than emitted as separate top-level
 * translated blocks.
 */
class ListHandler extends AbstractBlockHandler {

	/**
	 * Translate a single core/list block.
	 *
	 * @param array<string, mixed> $block A block from parse_blocks().
	 * @return array<string, mixed> The translated block.
	 */
	public function translate( array $block ): array {
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$style = is_array( $attrs['style'] ?? null ) ? $attrs['style'] : array();

		return array(
			'blockName'  => 'core/list',
			'ordered'    => $this->build_ordered( $attrs ),
			'reversed'   => $this->build_reversed( $attrs ),
			'start'      => $this->build_start( $attrs ),
			'type'       => $this->nullable_string( $attrs['type'] ?? null ),
			'colors'     => $this->build_colors( $attrs, $style ),
			'typography' => $this->build_typography( $attrs, $style ),
			'spacing'    => $this->build_spacing( $style ),
			'items'      => $this->build_items( $block ),
		);
	}

	/**
	 * Build the structural subtree for a list (used for nested lists).
	 *
	 * Omits blockName and block-level style fields — nested lists in the
	 * sample carry structure-only attrs on the child `core/list` block.
	 *
	 * @param array<string, mixed> $list_block A parsed core/list block.
	 * @return array<string, mixed> Nested list tree.
	 */
	private function build_list_tree( array $list_block ): array {
		$attrs = is_array( $list_block['attrs'] ?? null ) ? $list_block['attrs'] : array();

		return array(
			'ordered'  => $this->build_ordered( $attrs ),
			'reversed' => $this->build_reversed( $attrs ),
			'start'    => $this->build_start( $attrs ),
			'type'     => $this->nullable_string( $attrs['type'] ?? null ),
			'items'    => $this->build_items( $list_block ),
		);
	}

	/**
	 * Walk list-item inner blocks and build the translated items array.
	 *
	 * @param array<string, mixed> $list_block A parsed core/list block.
	 * @return array<int, array<string, mixed>> Translated list items.
	 */
	private function build_items( array $list_block ): array {
		$items        = array();
		$inner_blocks = $list_block['innerBlocks'] ?? null;

		if ( ! is_array( $inner_blocks ) ) {
			return $items;
		}

		foreach ( $inner_blocks as $inner ) {
			if ( ! is_array( $inner ) ) {
				continue;
			}

			$name = $inner['blockName'] ?? null;
			if ( 'core/list-item' !== $name ) {
				continue;
			}

			$items[] = $this->translate_list_item( $inner );
		}

		return $items;
	}

	/**
	 * Translate a single core/list-item block.
	 *
	 * @param array<string, mixed> $list_item A parsed core/list-item block.
	 * @return array<string, mixed> Item with content AST and optional nested list.
	 */
	private function translate_list_item( array $list_item ): array {
		$html   = $this->list_item_inline_html( $list_item );
		$nested = null;

		$inner_blocks = $list_item['innerBlocks'] ?? null;
		if ( is_array( $inner_blocks ) ) {
			foreach ( $inner_blocks as $inner ) {
				if ( ! is_array( $inner ) ) {
					continue;
				}

				if ( 'core/list' === ( $inner['blockName'] ?? null ) ) {
					$nested = $this->build_list_tree( $inner );
					break;
				}
			}
		}

		return array(
			'content' => $this->inline_parser->parse( $html ),
			'nested'  => $nested,
		);
	}

	/**
	 * Concatenate only the HTML string fragments from a list-item's innerContent.
	 *
	 * Skips null placeholders that stand in for inner blocks so nested list
	 * markup is not parsed twice.
	 *
	 * @param array<string, mixed> $list_item_block A parsed core/list-item block.
	 * @return string Inline HTML for the list-item's own text.
	 */
	private function list_item_inline_html( array $list_item_block ): string {
		$parts = array();

		foreach ( $list_item_block['innerContent'] ?? array() as $chunk ) {
			if ( is_string( $chunk ) ) {
				$parts[] = $chunk;
			}
		}

		return implode( '', $parts );
	}

	/**
	 * Resolve the ordered boolean (defaults to false when absent).
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return bool Whether the list is ordered.
	 */
	private function build_ordered( array $attrs ): bool {
		return true === ( $attrs['ordered'] ?? false );
	}

	/**
	 * Resolve the reversed boolean (defaults to false when absent).
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return bool Whether the ordered list is reversed.
	 */
	private function build_reversed( array $attrs ): bool {
		return true === ( $attrs['reversed'] ?? false );
	}

	/**
	 * Resolve the start attribute for ordered lists.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return int|null Start value, or null when absent.
	 */
	private function build_start( array $attrs ): ?int {
		if ( ! array_key_exists( 'start', $attrs ) ) {
			return null;
		}

		$start = $attrs['start'];
		if ( is_int( $start ) ) {
			return $start > 0 ? $start : null;
		}

		if ( is_float( $start ) ) {
			$as_int = (int) $start;
			return $as_int > 0 ? $as_int : null;
		}

		if ( is_string( $start ) && is_numeric( $start ) ) {
			$as_int = (int) $start;
			return $as_int > 0 ? $as_int : null;
		}

		return null;
	}
}
