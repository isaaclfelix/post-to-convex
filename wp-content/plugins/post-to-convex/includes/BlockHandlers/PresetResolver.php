<?php
/**
 * Resolves WordPress theme.json preset tokens to concrete CSS values.
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
 * Resolves color, font-size, and spacing preset slugs against the active theme's
 * theme.json data, returning concrete CSS values (e.g. hex colors, rem sizes).
 *
 * Returns null when the requested slug is not present in the active palette.
 */
class PresetResolver {

	/**
	 * Resolve a color preset slug to its hex/CSS value.
	 *
	 * @param string $slug Preset slug (e.g. 'vivid-red').
	 * @return string|null Resolved color, or null when not found.
	 */
	public function resolve_color( string $slug ): ?string {
		if ( '' === $slug ) {
			return null;
		}

		$palette = wp_get_global_settings( array( 'color', 'palette' ) );

		return $this->lookup_preset_value( $palette, $slug, 'color' );
	}

	/**
	 * Resolve a font-size preset slug to its CSS value.
	 *
	 * @param string $slug Preset slug (e.g. 'small', 'x-large').
	 * @return string|null Resolved font size, or null when not found.
	 */
	public function resolve_font_size( string $slug ): ?string {
		if ( '' === $slug ) {
			return null;
		}

		$sizes = wp_get_global_settings( array( 'typography', 'fontSizes' ) );

		return $this->lookup_preset_value( $sizes, $slug, 'size' );
	}

	/**
	 * Resolve a spacing preset slug to its CSS value.
	 *
	 * @param string $slug Preset slug (e.g. '50').
	 * @return string|null Resolved spacing size, or null when not found.
	 */
	public function resolve_spacing( string $slug ): ?string {
		if ( '' === $slug ) {
			return null;
		}

		$sizes = wp_get_global_settings( array( 'spacing', 'spacingSizes' ) );

		return $this->lookup_preset_value( $sizes, $slug, 'size' );
	}

	/**
	 * Extract a preset slug from a value that may be a `var:preset|<kind>|<slug>`
	 * token, a CSS `var( --wp--preset--<kind>--<slug> )` expression, or a
	 * literal CSS value.
	 *
	 * @param string $value The raw value as it appears in block attributes.
	 * @param string $kind  Preset kind ('color', 'spacing', 'font-size'). Spelled
	 *                     with a hyphen in the CSS variable form.
	 * @return string|null Slug if the value references a preset, otherwise null.
	 */
	public function extract_preset_slug( string $value, string $kind ): ?string {
		$kind_pattern = preg_quote( $kind, '/' );

		$matches = array();
		if ( 1 === preg_match( '/^var:preset\|' . $kind_pattern . '\|(.+)$/', $value, $matches ) ) {
			return $matches[1];
		}

		if ( 1 === preg_match( '/^var\(\s*--wp--preset--' . $kind_pattern . '--([^\s)]+)\s*\)$/', $value, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Origin priority for theme.json preset lookups (most specific first).
	 *
	 * `wp_get_global_settings()` returns palette data grouped by origin
	 * (`default`, `theme`, `custom`) when an active theme.json defines presets
	 * at multiple origins; the most specific origin wins.
	 *
	 * @var array<int, string>
	 */
	private const ORIGIN_PRIORITY = array( 'custom', 'theme', 'default' );

	/**
	 * Walk a settings node (which may be a flat list of entries or an
	 * origin-grouped map of lists) and return the value of the entry whose
	 * `slug` matches.
	 *
	 * Flat lists may contain duplicate slugs because `wp_get_global_settings()`
	 * concatenates palettes from each origin in increasing-specificity order
	 * (default -> theme -> custom). The *last* matching entry wins so theme
	 * and user customizations override core defaults.
	 *
	 * Origin-grouped maps are walked in the same priority order via
	 * {@see self::ORIGIN_PRIORITY}.
	 *
	 * @param mixed  $node      Settings node from wp_get_global_settings().
	 * @param string $slug      Preset slug to find.
	 * @param string $value_key Key within an entry that holds the resolved value (e.g. 'color', 'size').
	 * @return string|null The matching value, or null when not found.
	 */
	private function lookup_preset_value( mixed $node, string $slug, string $value_key ): ?string {
		if ( ! is_array( $node ) ) {
			return null;
		}

		$found = null;
		foreach ( $node as $key => $entry ) {
			if ( is_int( $key ) && is_array( $entry ) && isset( $entry['slug'] ) && $entry['slug'] === $slug ) {
				$value = $entry[ $value_key ] ?? null;
				if ( is_string( $value ) ) {
					$found = $value;
				}
			}
		}

		if ( null !== $found ) {
			return $found;
		}

		foreach ( self::ORIGIN_PRIORITY as $origin ) {
			if ( isset( $node[ $origin ] ) && is_array( $node[ $origin ] ) ) {
				$value = $this->lookup_preset_value( $node[ $origin ], $slug, $value_key );
				if ( null !== $value ) {
					return $value;
				}
			}
		}

		foreach ( $node as $key => $entries ) {
			if (
				is_string( $key )
				&& ! in_array( $key, self::ORIGIN_PRIORITY, true )
				&& is_array( $entries )
			) {
				$value = $this->lookup_preset_value( $entries, $slug, $value_key );
				if ( null !== $value ) {
					return $value;
				}
			}
		}

		return null;
	}
}
