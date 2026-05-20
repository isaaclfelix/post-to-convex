<?php
/**
 * Shared base class for block handlers that share preset-aware color,
 * typography, and spacing builders plus inline content parsing.
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
 * Common scaffolding for block handlers whose schema includes some combination
 * of WordPress' `colors` / `typography` / `spacing` style metadata and an
 * inline-content AST.
 *
 * Subclasses implement {@see BlockHandlerInterface::translate()} and compose
 * their output from the protected builders below. The base class owns the
 * shared dependencies ({@see InlineTreeParser} and {@see PresetResolver}) plus
 * the small utility helpers (`nullable_string`, the `{ token, resolved }`
 * preset shape, the side-collapsing spacing builder).
 *
 * Output sub-shapes produced by the builders here (each `{ token, resolved }`
 * preset entry has both fields nullable):
 *
 *   colors:     { text, background, link }
 *   typography: { fontSize, fontStyle, fontWeight, lineHeight, letterSpacing,
 *                 textDecoration, textTransform, writingMode }
 *   spacing:    { padding: { top, right, bottom, left } | null,
 *                 margin:  { top, right, bottom, left } | null }
 */
abstract class AbstractBlockHandler implements BlockHandlerInterface {

	/**
	 * Inline tree parser dependency.
	 *
	 * @var InlineTreeParser
	 */
	protected InlineTreeParser $inline_parser;

	/**
	 * Preset resolver dependency.
	 *
	 * @var PresetResolver
	 */
	protected PresetResolver $preset_resolver;

	/**
	 * Constructor.
	 *
	 * @param InlineTreeParser $inline_parser   Inline tree parser.
	 * @param PresetResolver   $preset_resolver Preset resolver.
	 */
	public function __construct( InlineTreeParser $inline_parser, PresetResolver $preset_resolver ) {
		$this->inline_parser   = $inline_parser;
		$this->preset_resolver = $preset_resolver;
	}

	/**
	 * Translate a single parsed block.
	 *
	 * @param array<string, mixed> $block A block from parse_blocks().
	 * @return array<string, mixed> The translated block.
	 */
	abstract public function translate( array $block ): array;

	/**
	 * Coerce a value to a non-empty string, or null otherwise.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null Non-empty string, or null.
	 */
	protected function nullable_string( mixed $value ): ?string {
		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Build the colors map (text, background, link).
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @param array<string, mixed> $style The `style` sub-tree of block attributes.
	 * @return array<string, array<string, string|null>|null> Colors map.
	 */
	protected function build_colors( array $attrs, array $style ): array {
		return array(
			'text'       => $this->build_color_preset( $attrs['textColor'] ?? null ),
			'background' => $this->build_color_preset( $attrs['backgroundColor'] ?? null ),
			'link'       => $this->build_link_color( $style ),
		);
	}

	/**
	 * Build a `{ token, resolved }` preset entry for a color slug.
	 *
	 * @param mixed $slug Color preset slug from block attributes.
	 * @return array<string, string|null>|null Preset entry or null when not a slug.
	 */
	protected function build_color_preset( mixed $slug ): ?array {
		if ( ! is_string( $slug ) || '' === $slug ) {
			return null;
		}

		return array(
			'token'    => $slug,
			'resolved' => $this->preset_resolver->resolve_color( $slug ),
		);
	}

	/**
	 * Build the link-color entry from `style.elements.link.color.text`.
	 *
	 * The value is either a `var:preset|color|<slug>` token (resolved) or a
	 * literal CSS color (passed through with `token` null).
	 *
	 * @param array<string, mixed> $style The `style` sub-tree of block attributes.
	 * @return array<string, string|null>|null Preset entry or null when missing.
	 */
	protected function build_link_color( array $style ): ?array {
		$elements = is_array( $style['elements'] ?? null ) ? $style['elements'] : array();
		$link     = is_array( $elements['link'] ?? null ) ? $elements['link'] : array();
		$color    = is_array( $link['color'] ?? null ) ? $link['color'] : array();
		$value    = $color['text'] ?? null;

		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		$slug = $this->preset_resolver->extract_preset_slug( $value, 'color' );
		if ( null !== $slug ) {
			return array(
				'token'    => $slug,
				'resolved' => $this->preset_resolver->resolve_color( $slug ),
			);
		}

		return array(
			'token'    => null,
			'resolved' => $value,
		);
	}

	/**
	 * Build the typography map.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @param array<string, mixed> $style The `style` sub-tree of block attributes.
	 * @return array<string, mixed> Typography map.
	 */
	protected function build_typography( array $attrs, array $style ): array {
		$typography = is_array( $style['typography'] ?? null ) ? $style['typography'] : array();

		return array(
			'fontSize'       => $this->build_font_size( $attrs, $typography ),
			'fontStyle'      => $this->nullable_string( $typography['fontStyle'] ?? null ),
			'fontWeight'     => $this->nullable_string( $typography['fontWeight'] ?? null ),
			'lineHeight'     => $this->nullable_string( $typography['lineHeight'] ?? null ),
			'letterSpacing'  => $this->nullable_string( $typography['letterSpacing'] ?? null ),
			'textDecoration' => $this->nullable_string( $typography['textDecoration'] ?? null ),
			'textTransform'  => $this->nullable_string( $typography['textTransform'] ?? null ),
			'writingMode'    => $this->nullable_string( $typography['writingMode'] ?? null ),
		);
	}

	/**
	 * Build the font-size preset entry.
	 *
	 * Prefers `attrs.fontSize` (preset slug) and falls back to
	 * `attrs.style.typography.fontSize` (literal CSS value).
	 *
	 * @param array<string, mixed> $attrs      Block attributes.
	 * @param array<string, mixed> $typography The `style.typography` sub-tree.
	 * @return array<string, string|null>|null Preset entry or null when no font size is set.
	 */
	protected function build_font_size( array $attrs, array $typography ): ?array {
		$preset = $attrs['fontSize'] ?? null;
		if ( is_string( $preset ) && '' !== $preset ) {
			return array(
				'token'    => $preset,
				'resolved' => $this->preset_resolver->resolve_font_size( $preset ),
			);
		}

		$custom = $typography['fontSize'] ?? null;
		if ( is_string( $custom ) && '' !== $custom ) {
			return array(
				'token'    => null,
				'resolved' => $custom,
			);
		}

		return null;
	}

	/**
	 * Build the spacing map (padding, margin).
	 *
	 * @param array<string, mixed> $style The `style` sub-tree of block attributes.
	 * @return array<string, array<string, mixed>|null> Spacing map.
	 */
	protected function build_spacing( array $style ): array {
		$spacing = is_array( $style['spacing'] ?? null ) ? $style['spacing'] : array();

		return array(
			'padding' => $this->build_spacing_sides( $spacing['padding'] ?? null ),
			'margin'  => $this->build_spacing_sides( $spacing['margin'] ?? null ),
		);
	}

	/**
	 * Build a spacing sides map (top/right/bottom/left).
	 *
	 * Returns null when the input is not an array or all four sides are
	 * missing — keeps the JSON tight by omitting empty objects.
	 *
	 * @param mixed $sides Raw spacing sides value from block attributes.
	 * @return array<string, array<string, string|null>|null>|null Sides map or null.
	 */
	protected function build_spacing_sides( mixed $sides ): ?array {
		if ( ! is_array( $sides ) ) {
			return null;
		}

		$result = array(
			'top'    => $this->build_spacing_side( $sides['top'] ?? null ),
			'right'  => $this->build_spacing_side( $sides['right'] ?? null ),
			'bottom' => $this->build_spacing_side( $sides['bottom'] ?? null ),
			'left'   => $this->build_spacing_side( $sides['left'] ?? null ),
		);

		foreach ( $result as $side ) {
			if ( null !== $side ) {
				return $result;
			}
		}

		return null;
	}

	/**
	 * Build a spacing side preset entry.
	 *
	 * Accepts `var:preset|spacing|<slug>` tokens (resolved against theme.json)
	 * and literal CSS values (passed through with `token` null).
	 *
	 * @param mixed $value Raw side value.
	 * @return array<string, string|null>|null Preset entry or null when missing.
	 */
	protected function build_spacing_side( mixed $value ): ?array {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		$slug = $this->preset_resolver->extract_preset_slug( $value, 'spacing' );
		if ( null !== $slug ) {
			return array(
				'token'    => $slug,
				'resolved' => $this->preset_resolver->resolve_spacing( $slug ),
			);
		}

		return array(
			'token'    => null,
			'resolved' => $value,
		);
	}
}
