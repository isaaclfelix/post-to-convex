<?php
/**
 * Translates core/image blocks into the JSON schema consumed by the headless renderer.
 *
 * @package PostToConvex
 */

declare( strict_types=1 );

namespace PostToConvex\BlockHandlers;

use PostToConvex\BlockTranslationException;
use PostToConvex\Dom;
use PostToConvex\MediaSync;

/**
 * Security check.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Translates a parsed `core/image` block into the JSON-ready array form.
 *
 * Requires a Media Library attachment id (`attrs.id`). URL-only blocks (Insert
 * from URL) throw {@see BlockTranslationException}. Ensures the attachment is
 * synced to Convex before emitting `mediaId`.
 *
 * Output schema (each preset field uses { token, resolved }):
 *
 *   {
 *     blockName:   'core/image',
 *     mediaId:     string,
 *     alt:         string,
 *     caption:     [ inline AST ],
 *     align:       'left' | 'center' | 'right' | 'wide' | 'full' | null,
 *     className:   string | null,
 *     sizeSlug:    string | null,
 *     width:       string | null,
 *     height:      string | null,
 *     aspectRatio: string | null,
 *     scale:       string | null,
 *     lightbox:    { enabled: bool } | null,
 *     link:        { destination: string, url: string | null },
 *     colors:      { text, background, link, border, duotone },
 *     spacing:     { padding, margin },
 *     border:      { width, radius, color }
 *   }
 */
class ImageHandler extends AbstractBlockHandler {

	/**
	 * Media sync dependency.
	 *
	 * @var MediaSync
	 */
	private MediaSync $media_sync;

	/**
	 * Constructor.
	 *
	 * @param InlineTreeParser $inline_parser   Inline tree parser.
	 * @param PresetResolver   $preset_resolver Preset resolver.
	 * @param MediaSync        $media_sync      Media sync service.
	 */
	public function __construct( InlineTreeParser $inline_parser, PresetResolver $preset_resolver, MediaSync $media_sync ) {
		parent::__construct( $inline_parser, $preset_resolver );
		$this->media_sync = $media_sync;
	}

	/**
	 * Translate a single core/image block.
	 *
	 * @param array<string, mixed> $block A block from parse_blocks().
	 * @return array<string, mixed> The translated block.
	 * @throws BlockTranslationException When the block cannot be synced.
	 */
	public function translate( array $block ): array {
		$attrs      = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$style      = is_array( $attrs['style'] ?? null ) ? $attrs['style'] : array();
		$inner_html = is_string( $block['innerHTML'] ?? null ) ? $block['innerHTML'] : '';

		$attachment_id = $this->resolve_attachment_id( $attrs );
		$media_id      = $this->media_sync->ensure_attachment_synced( $attachment_id );

		if ( ! is_string( $media_id ) || '' === $media_id ) {
			$reason = $this->media_sync->get_sync_block_reason( $attachment_id );

			throw new BlockTranslationException(
				is_string( $reason ) && '' !== $reason
					? $reason
					: __( 'Could not sync image to Convex.', 'post-to-convex' )
			);
		}

		return array(
			'blockName'   => 'core/image',
			'mediaId'     => $media_id,
			'alt'         => is_string( $attrs['alt'] ?? null ) ? $attrs['alt'] : '',
			'caption'     => $this->build_caption( $inner_html ),
			'align'       => $this->nullable_string( $attrs['align'] ?? null ),
			'className'   => $this->nullable_string( $attrs['className'] ?? null ),
			'sizeSlug'    => $this->nullable_string( $attrs['sizeSlug'] ?? null ),
			'width'       => $this->nullable_string( $attrs['width'] ?? null ),
			'height'      => $this->nullable_string( $attrs['height'] ?? null ),
			'aspectRatio' => $this->nullable_string( $attrs['aspectRatio'] ?? null ),
			'scale'       => $this->nullable_string( $attrs['scale'] ?? null ),
			'lightbox'    => $this->build_lightbox( $attrs ),
			'link'        => $this->build_link( $attrs ),
			'colors'      => $this->build_image_colors( $attrs, $style ),
			'spacing'     => $this->build_spacing( $style ),
			'border'      => $this->build_border( $style, $attrs ),
		);
	}

	/**
	 * Require a positive WordPress attachment id.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return int Attachment post ID.
	 * @throws BlockTranslationException When id is missing (URL-only blocks).
	 */
	private function resolve_attachment_id( array $attrs ): int {
		$attachment_id = (int) ( $attrs['id'] ?? 0 );

		if ( $attachment_id <= 0 ) {
			throw new BlockTranslationException(
				__(
					'Image blocks must use an image from the Media Library. Insert from URL is not supported yet. Upload or pick a library image instead.',
					'post-to-convex'
				)
			);
		}

		return $attachment_id;
	}

	/**
	 * Parse figcaption innerHTML into an inline AST.
	 *
	 * @param string $inner_html Block innerHTML.
	 * @return array<int, array<string, mixed>> Caption inline AST (empty when absent).
	 */
	private function build_caption( string $inner_html ): array {
		if ( '' === trim( $inner_html ) ) {
			return array();
		}

		$fragment = Dom::string_to_dom_fragment( $inner_html );

		foreach ( $fragment->childNodes as $child ) {
			if ( ! $child instanceof \DOMElement ) {
				continue;
			}

			if ( 'figure' === strtolower( $child->tagName ) ) {
				foreach ( $child->childNodes as $figure_child ) {
					if ( $figure_child instanceof \DOMElement && 'figcaption' === strtolower( $figure_child->tagName ) ) {
						return $this->inline_parser->parse_node( $figure_child );
					}
				}
			}

			if ( 'figcaption' === strtolower( $child->tagName ) ) {
				return $this->inline_parser->parse_node( $child );
			}
		}

		return array();
	}

	/**
	 * Build the lightbox settings object when present on attrs.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return array{enabled: bool}|null Lightbox settings or null when absent.
	 */
	private function build_lightbox( array $attrs ): ?array {
		$lightbox = $attrs['lightbox'] ?? null;

		if ( ! is_array( $lightbox ) || ! array_key_exists( 'enabled', $lightbox ) ) {
			return null;
		}

		return array(
			'enabled' => true === $lightbox['enabled'],
		);
	}

	/**
	 * Build link destination metadata from block attrs.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return array{destination: string, url: string|null} Link metadata.
	 */
	private function build_link( array $attrs ): array {
		$destination = $attrs['linkDestination'] ?? 'none';

		if ( ! is_string( $destination ) || '' === $destination ) {
			$destination = 'none';
		}

		$url = $this->nullable_string( $attrs['href'] ?? null );

		return array(
			'destination' => $destination,
			'url'         => $url,
		);
	}

	/**
	 * Build the colors map including border and duotone presets.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @param array<string, mixed> $style The `style` sub-tree of block attributes.
	 * @return array<string, array<string, string|null>|null> Colors map.
	 */
	private function build_image_colors( array $attrs, array $style ): array {
		$colors = $this->build_colors( $attrs, $style );

		$colors['border']  = $this->build_color_preset( $attrs['borderColor'] ?? null );
		$colors['duotone'] = $this->build_duotone_preset( $style );

		return $colors;
	}

	/**
	 * Build a duotone preset entry from `style.color.duotone`.
	 *
	 * @param array<string, mixed> $style The `style` sub-tree of block attributes.
	 * @return array<string, string|null>|null Preset entry or null when missing.
	 */
	private function build_duotone_preset( array $style ): ?array {
		$color = is_array( $style['color'] ?? null ) ? $style['color'] : array();
		$value = $color['duotone'] ?? null;

		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		$slug = $this->preset_resolver->extract_preset_slug( $value, 'duotone' );

		if ( null !== $slug ) {
			return array(
				'token'    => $slug,
				'resolved' => null,
			);
		}

		return array(
			'token'    => null,
			'resolved' => $value,
		);
	}

	/**
	 * Build border width, radius, and color from attrs.
	 *
	 * @param array<string, mixed> $style The `style` sub-tree of block attributes.
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return array{width: string|null, radius: string|null, color: array<string, string|null>|null} Border map.
	 */
	private function build_border( array $style, array $attrs ): array {
		$border_style = is_array( $style['border'] ?? null ) ? $style['border'] : array();

		$inline_color = $border_style['color'] ?? null;
		$color_entry  = null;

		if ( is_string( $inline_color ) && '' !== $inline_color ) {
			$slug = $this->preset_resolver->extract_preset_slug( $inline_color, 'color' );

			if ( null !== $slug ) {
				$color_entry = array(
					'token'    => $slug,
					'resolved' => $this->preset_resolver->resolve_color( $slug ),
				);
			} else {
				$color_entry = array(
					'token'    => null,
					'resolved' => $inline_color,
				);
			}
		}

		if ( null === $color_entry ) {
			$color_entry = $this->build_color_preset( $attrs['borderColor'] ?? null );
		}

		return array(
			'width'  => $this->nullable_string( $border_style['width'] ?? null ),
			'radius' => $this->nullable_string( $border_style['radius'] ?? null ),
			'color'  => $color_entry,
		);
	}
}
