<?php
/**
 * Shared helpers for block-handler test cases.
 *
 * @package Post_To_Convex
 */

declare( strict_types=1 );

namespace PostToConvex\Tests\Support;

use PostToConvex\BlockHandlers\PresetResolver;

/**
 * Trait bundling the two patterns every per-block handler test reuses:
 *
 * - {@see make_fake_resolver()} returns an anonymous subclass of
 *   {@see PresetResolver} that resolves color / font-size / spacing slugs
 *   against caller-provided maps. Keeps handler tests decoupled from the
 *   active theme.json.
 * - {@see load_blocks_of_type()} reads a sample HTML fixture from
 *   `tests/data/`, runs it through `parse_blocks()`, and recursively flattens
 *   the result so only blocks with the requested block name remain (in
 *   document order). Lets index-based variant assertions stay stable even
 *   when the sample puts some blocks inside wrappers like `core/group`.
 */
trait BlockHandlerTestSupport {

	/**
	 * Build a stub {@see PresetResolver} backed by deterministic lookup maps.
	 *
	 * Slugs not present in the supplied maps resolve to null. The empty-string
	 * short-circuits in the real resolver are reproduced here so the stub
	 * stays a drop-in replacement.
	 *
	 * @param array<string, string> $palette    Color slug => resolved CSS value.
	 * @param array<string, string> $font_sizes Font-size slug => resolved CSS value.
	 * @param array<string, string> $spacing    Spacing slug => resolved CSS value.
	 * @return PresetResolver Fake resolver.
	 */
	protected function make_fake_resolver(
		array $palette = array(),
		array $font_sizes = array(),
		array $spacing = array()
	): PresetResolver {
		return new class( $palette, $font_sizes, $spacing ) extends PresetResolver {

			/**
			 * Color palette: slug => resolved CSS value.
			 *
			 * @var array<string, string>
			 */
			private array $palette;

			/**
			 * Font-size scale: slug => resolved CSS value.
			 *
			 * @var array<string, string>
			 */
			private array $font_sizes;

			/**
			 * Spacing scale: slug => resolved CSS value.
			 *
			 * @var array<string, string>
			 */
			private array $spacing;

			/**
			 * Capture the lookup maps for later resolution.
			 *
			 * @param array<string, string> $palette    Color map.
			 * @param array<string, string> $font_sizes Font-size map.
			 * @param array<string, string> $spacing    Spacing map.
			 */
			public function __construct( array $palette, array $font_sizes, array $spacing ) {
				$this->palette    = $palette;
				$this->font_sizes = $font_sizes;
				$this->spacing    = $spacing;
			}

			/**
			 * Resolve a color preset slug from the configured palette.
			 *
			 * @param string $slug Color slug.
			 * @return string|null Resolved value or null when unknown.
			 */
			public function resolve_color( string $slug ): ?string {
				if ( '' === $slug ) {
					return null;
				}

				return $this->palette[ $slug ] ?? null;
			}

			/**
			 * Resolve a font-size preset slug from the configured scale.
			 *
			 * @param string $slug Font-size slug.
			 * @return string|null Resolved value or null when unknown.
			 */
			public function resolve_font_size( string $slug ): ?string {
				if ( '' === $slug ) {
					return null;
				}

				return $this->font_sizes[ $slug ] ?? null;
			}

			/**
			 * Resolve a spacing preset slug from the configured scale.
			 *
			 * @param string $slug Spacing slug.
			 * @return string|null Resolved value or null when unknown.
			 */
			public function resolve_spacing( string $slug ): ?string {
				if ( '' === $slug ) {
					return null;
				}

				return $this->spacing[ $slug ] ?? null;
			}
		};
	}

	/**
	 * Load a sample HTML fixture and return every block of the requested name
	 * in document order, descending into innerBlocks so blocks nested inside
	 * wrapper blocks (e.g. `core/group`) are still surfaced.
	 *
	 * @param string $sample_filename Filename inside `tests/data/`.
	 * @param string $block_name      Block name to filter on (e.g. 'core/heading').
	 * @return array<int, array<string, mixed>> Matching blocks.
	 */
	protected function load_blocks_of_type( string $sample_filename, string $block_name ): array {
		$path = __DIR__ . '/../data/' . $sample_filename;
		$html = file_exists( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture read; wp_remote_get is for URLs.

		$blocks = parse_blocks( is_string( $html ) ? $html : '' );

		return $this->flatten_blocks_by_name( $blocks, $block_name );
	}

	/**
	 * Recursively collect every block with the given block name in document
	 * order.
	 *
	 * @param array<int, array<string, mixed>> $blocks     Parsed blocks.
	 * @param string                           $block_name Block name to match.
	 * @return array<int, array<string, mixed>> Matching blocks.
	 */
	private function flatten_blocks_by_name( array $blocks, string $block_name ): array {
		$out = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = $block['blockName'] ?? null;
			if ( $block_name === $name ) {
				$out[] = $block;
				continue;
			}

			$inner = $block['innerBlocks'] ?? null;
			if ( is_array( $inner ) && ! empty( $inner ) ) {
				foreach ( $this->flatten_blocks_by_name( $inner, $block_name ) as $nested ) {
					$out[] = $nested;
				}
			}
		}

		return $out;
	}
}
