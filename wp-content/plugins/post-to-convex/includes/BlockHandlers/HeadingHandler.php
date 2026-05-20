<?php
/**
 * Translates core/heading blocks into the JSON schema consumed by the headless renderer.
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
 * Translates a parsed `core/heading` block into the JSON-ready array form.
 *
 * Output schema (each field that accepts presets uses { token, resolved } where
 * `token` is the theme.json slug and `resolved` is the concrete CSS value
 * looked up against the active theme — either may be null):
 *
 *   {
 *     blockName:  'core/heading',
 *     level:      1..6,
 *     align:      'wide' | 'full' | null,
 *     textAlign:  'left' | 'center' | 'right' | null,
 *     colors:     { text, background, link },
 *     typography: { fontSize, fontStyle, fontWeight, lineHeight,
 *                   letterSpacing, textDecoration, textTransform, writingMode },
 *     spacing:    { padding: { top, right, bottom, left } | null,
 *                   margin:  { top, right, bottom, left } | null },
 *     content:    [ inline AST ]
 *   }
 */
class HeadingHandler extends AbstractBlockHandler {

	/**
	 * Default heading level when omitted from block attributes (matches the
	 * Gutenberg editor default).
	 *
	 * @var int
	 */
	private const DEFAULT_LEVEL = 2;

	/**
	 * Translate a single core/heading block.
	 *
	 * @param array<string, mixed> $block A block from parse_blocks().
	 * @return array<string, mixed> The translated block.
	 */
	public function translate( array $block ): array {
		$attrs      = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$style      = is_array( $attrs['style'] ?? null ) ? $attrs['style'] : array();
		$inner_html = is_string( $block['innerHTML'] ?? null ) ? $block['innerHTML'] : '';

		return array(
			'blockName'  => 'core/heading',
			'level'      => $this->build_level( $attrs ),
			'align'      => $this->nullable_string( $attrs['align'] ?? null ),
			'textAlign'  => $this->nullable_string( $attrs['textAlign'] ?? null ),
			'colors'     => $this->build_colors( $attrs, $style ),
			'typography' => $this->build_typography( $attrs, $style ),
			'spacing'    => $this->build_spacing( $style ),
			'content'    => $this->inline_parser->parse( $inner_html ),
		);
	}

	/**
	 * Resolve the heading level, clamped to 1-6.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return int Heading level.
	 */
	private function build_level( array $attrs ): int {
		$level = isset( $attrs['level'] ) ? intval( $attrs['level'] ) : self::DEFAULT_LEVEL;

		if ( $level < 1 || $level > 6 ) {
			return self::DEFAULT_LEVEL;
		}

		return $level;
	}
}
