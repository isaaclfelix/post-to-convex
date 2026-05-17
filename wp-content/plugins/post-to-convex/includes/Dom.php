<?php
/**
 * DOM functions.
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
 * DOM functions.
 */
class Dom {

	/**
	 * DOMDocument load options.
	 *
	 * @var int
	 */
	private const LOAD_OPTIONS = LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD;

	/**
	 * DOMDocument fragment wrapper tag.
	 *
	 * @var string
	 */
	private const FRAGMENT_WRAPPER_TAG = 'div';

	/**
	 * Ensure a string is valid UTF-8 before DOM parsing.
	 *
	 * @param string $html Raw HTML.
	 * @return string UTF-8 HTML.
	 */
	private static function normalize_utf8( string $html ): string {
		if ( mb_check_encoding( $html, 'UTF-8' ) ) {
			return $html;
		}

		$encoding = mb_detect_encoding( $html, mb_detect_order(), true );

		return mb_convert_encoding( $html, 'UTF-8', is_string( $encoding ) ? $encoding : 'UTF-8' );
	}

	/**
	 * Create a UTF-8 DOM document configured for HTML parsing.
	 *
	 * @return \DOMDocument
	 */
	private static function create_dom_document(): \DOMDocument {
		$dom                     = new \DOMDocument( '1.0', 'UTF-8' );
		$dom->encoding           = 'UTF-8';
		$dom->preserveWhiteSpace = true;
		$dom->substituteEntities = false;

		return $dom;
	}

	/**
	 * Load HTML into a document, suppressing libxml warnings.
	 *
	 * @param \DOMDocument $dom  Target document.
	 * @param string       $html HTML to parse.
	 * @return void
	 */
	private static function load_html_into_document( \DOMDocument $dom, string $html ): void {
		$previous = libxml_use_internal_errors( true );

		$dom->loadHTML(
			'<?xml encoding="utf-8" ?>' . $html,
			self::LOAD_OPTIONS
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
	}

	/**
	 * Parse an HTML string into a document fragment (no html/body wrapper).
	 *
	 * @param string $html The HTML string to convert.
	 * @return \DOMDocumentFragment The parsed fragment.
	 */
	public static function string_to_dom_fragment( string $html ): \DOMDocumentFragment {
		$html       = self::normalize_utf8( $html );
		$dom        = self::create_dom_document();
		$wrapper_id = 'ptc-' . bin2hex( random_bytes( 8 ) );

		self::load_html_into_document(
			$dom,
			sprintf(
				'<%1$s id="%2$s">%3$s</%1$s>',
				self::FRAGMENT_WRAPPER_TAG,
				$wrapper_id,
				$html
			)
		);

		$fragment = $dom->createDocumentFragment();
		$wrapper  = $dom->getElementById( $wrapper_id );

		if ( null !== $wrapper ) {
			while ( $wrapper->firstChild ) {
				$fragment->appendChild( $wrapper->firstChild );
			}
		}

		return $fragment;
	}

	/**
	 * Convert a string to a DOM document without implied html/body wrappers.
	 *
	 * Prefer string_to_dom_fragment() when parsing block-level HTML snippets.
	 *
	 * @param string $html The HTML string to convert.
	 * @return \DOMDocument The DOM document.
	 */
	public static function string_to_dom_document( string $html ): \DOMDocument {
		$html = self::normalize_utf8( $html );
		$dom  = self::create_dom_document();

		self::load_html_into_document( $dom, $html );

		return $dom;
	}

	/**
	 * Convert a DOM fragment to an HTML string.
	 *
	 * @param \DOMDocumentFragment $fragment The fragment to convert.
	 * @return string The HTML string.
	 */
	public static function dom_fragment_to_string( \DOMDocumentFragment $fragment ): string {
		$dom = $fragment->ownerDocument;

		if ( null === $dom ) {
			return '';
		}

		$html = '';

		foreach ( $fragment->childNodes as $child ) {
			$part = $dom->saveHTML( $child );

			if ( false !== $part ) {
				$html .= $part;
			}
		}

		return $html;
	}

	/**
	 * Convert a DOM document to an HTML string (top-level nodes only).
	 *
	 * @param \DOMDocument $dom The DOM document to convert.
	 * @return string The HTML string.
	 */
	public static function dom_document_to_string( \DOMDocument $dom ): string {
		$html = '';

		foreach ( $dom->childNodes as $child ) {
			if ( XML_PI_NODE === $child->nodeType ) {
				continue;
			}

			$part = $dom->saveHTML( $child );

			if ( false !== $part ) {
				$html .= $part;
			}
		}

		return $html;
	}

	/**
	 * Get the text content of a DOM node (document, fragment, or element).
	 *
	 * @param \DOMNode $node The node to read.
	 * @return string The text content.
	 */
	public static function get_text_content( \DOMNode $node ): string {
		return $node->textContent ?? '';
	}
}
