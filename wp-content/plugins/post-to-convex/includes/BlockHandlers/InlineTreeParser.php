<?php
/**
 * Parses inline HTML into a canonical recursive AST.
 *
 * @package PostToConvex
 */

declare( strict_types=1 );

namespace PostToConvex\BlockHandlers;

use PostToConvex\Dom;

/**
 * Security check.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Parses inline HTML (plain text plus optional <strong>, <b>, <em>, <i>, <a>,
 * <mark>) into a canonical recursive AST suitable for JSON serialization and
 * React rendering.
 *
 * Output node shapes (each emitted as an associative array):
 *
 *   - { type: 'text',   text }
 *   - { type: 'strong', children: [...] }
 *   - { type: 'em',     children: [...] }
 *   - { type: 'link',   attrs: { href, target?, rel? }, children: [...] }
 *   - { type: 'mark',   attrs: { style?: { backgroundColor?, color? }, hasInlineColor }, children: [...] }
 *
 * Authoring nesting order in Gutenberg is non-deterministic (e.g.
 * <a><strong><em>x</em></strong></a> and <em><strong><a>x</a></strong></em>
 * are both valid markup for "bold italic linked text"). The parser
 * canonicalizes so that within a contiguous run of identically-marked text the
 * nesting is always, outermost first:
 *
 *   link > strong > em > mark > text
 *
 * Implementation: a two-pass algorithm. Pass 1 walks the DOM and emits a flat
 * ordered list of leaves where each leaf carries the union of marks active at
 * that point. Pass 2 folds the leaves into a tree by greedily wrapping
 * contiguous leaves that share a mark (with structurally equal attrs).
 */
class InlineTreeParser {

	/**
	 * Mark precedence — outermost wrapper first.
	 *
	 * @var array<int, string>
	 */
	private const MARK_PRECEDENCE = array( 'link', 'strong', 'em', 'mark' );

	/**
	 * Parse a string of inline HTML into a canonical AST.
	 *
	 * Unrecognized container elements (e.g. <span>, <h2>) are transparent —
	 * their children are walked as if they were direct children of the parent.
	 *
	 * @param string $html The HTML to parse.
	 * @return array<int, array<string, mixed>> The canonical inline AST.
	 */
	public function parse( string $html ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}

		$fragment = Dom::string_to_dom_fragment( $html );

		return $this->parse_node( $fragment );
	}

	/**
	 * Parse the children of an existing DOM node into a canonical AST.
	 *
	 * Useful when the caller already owns a DOM node (e.g. a heading element
	 * extracted from a block's innerHTML) and wants to skip the cost of
	 * re-parsing the surrounding wrapper.
	 *
	 * @param \DOMNode $node The container whose children should be walked.
	 * @return array<int, array<string, mixed>> The canonical inline AST.
	 */
	public function parse_node( \DOMNode $node ): array {
		$leaves = $this->collect_leaves( $node, array() );
		$leaves = $this->trim_outer_whitespace( $leaves );

		foreach ( $leaves as &$leaf ) {
			$leaf['marks'] = $this->order_marks( $leaf['marks'] );
		}
		unset( $leaf );

		return $this->fold_leaves_at_depth( $leaves, 0 );
	}

	/**
	 * Pass 1 — recursively walk a node, emitting one leaf per text node with
	 * the active mark set captured at that point.
	 *
	 * @param \DOMNode                         $node  The node to walk.
	 * @param array<int, array<string, mixed>> $marks Currently active marks.
	 * @return array<int, array<string, mixed>> Flat list of leaves: [ { text, marks }, ... ].
	 */
	private function collect_leaves( \DOMNode $node, array $marks ): array {
		$leaves = array();

		foreach ( $node->childNodes as $child ) {
			if ( XML_TEXT_NODE === $child->nodeType ) {
				$leaves[] = array(
					'text'  => is_string( $child->nodeValue ) ? $child->nodeValue : '',
					'marks' => $marks,
				);
				continue;
			}

			if ( ! ( $child instanceof \DOMElement ) ) {
				continue;
			}

			$mark = $this->element_to_mark( $child );

			if ( null === $mark ) {
				$leaves = array_merge( $leaves, $this->collect_leaves( $child, $marks ) );
				continue;
			}

			$leaves = array_merge(
				$leaves,
				$this->collect_leaves( $child, $this->push_mark( $marks, $mark ) )
			);
		}

		return $leaves;
	}

	/**
	 * Map a DOM element to a mark descriptor, or null when the element is not
	 * a recognized inline mark (and should therefore be flattened away).
	 *
	 * @param \DOMElement $element The element.
	 * @return array<string, mixed>|null Mark descriptor with 'kind' and optional 'attrs'.
	 */
	private function element_to_mark( \DOMElement $element ): ?array {
		switch ( strtolower( $element->tagName ) ) {
			case 'strong':
			case 'b':
				return array( 'kind' => 'strong' );

			case 'em':
			case 'i':
				return array( 'kind' => 'em' );

			case 'a':
				return array(
					'kind'  => 'link',
					'attrs' => $this->build_link_attrs( $element ),
				);

			case 'mark':
				return array(
					'kind'  => 'mark',
					'attrs' => $this->build_mark_attrs( $element ),
				);
		}

		return null;
	}

	/**
	 * Read href / target / rel from an anchor element.
	 *
	 * Optional attributes are omitted when empty so the resulting JSON only
	 * contains keys the consumer actually needs to handle.
	 *
	 * @param \DOMElement $element The anchor element.
	 * @return array<string, string> Link attribute map.
	 */
	private function build_link_attrs( \DOMElement $element ): array {
		$attrs = array( 'href' => $element->getAttribute( 'href' ) );

		$target = $element->getAttribute( 'target' );
		if ( '' !== $target ) {
			$attrs['target'] = $target;
		}

		$rel = $element->getAttribute( 'rel' );
		if ( '' !== $rel ) {
			$attrs['rel'] = $rel;
		}

		return $attrs;
	}

	/**
	 * Read style + class metadata from a `<mark>` element.
	 *
	 * @param \DOMElement $element The mark element.
	 * @return array<string, mixed> Mark attribute map.
	 */
	private function build_mark_attrs( \DOMElement $element ): array {
		$attrs = array(
			'hasInlineColor' => false !== strpos(
				$element->getAttribute( 'class' ),
				'has-inline-color'
			),
		);

		$style = $this->parse_inline_style( $element->getAttribute( 'style' ) );
		if ( ! empty( $style ) ) {
			$attrs['style'] = $style;
		}

		return $attrs;
	}

	/**
	 * Parse a CSS `style` attribute into a camelCase property/value map.
	 *
	 * @param string $style The raw style attribute value.
	 * @return array<string, string> Parsed declarations.
	 */
	private function parse_inline_style( string $style ): array {
		$result = array();

		foreach ( explode( ';', $style ) as $declaration ) {
			$colon_pos = strpos( $declaration, ':' );
			if ( false === $colon_pos ) {
				continue;
			}

			$prop  = strtolower( trim( substr( $declaration, 0, $colon_pos ) ) );
			$value = trim( substr( $declaration, $colon_pos + 1 ) );

			if ( '' === $prop || '' === $value ) {
				continue;
			}

			$camel            = $this->css_property_to_camel( $prop );
			$result[ $camel ] = $value;
		}

		return $result;
	}

	/**
	 * Convert a CSS property name to camelCase (e.g. background-color -> backgroundColor).
	 *
	 * @param string $prop The CSS property name.
	 * @return string The camelCase form.
	 */
	private function css_property_to_camel( string $prop ): string {
		$camel = preg_replace_callback(
			'/-([a-z])/',
			static fn( array $matches ): string => strtoupper( $matches[1] ),
			$prop
		);

		return is_string( $camel ) ? $camel : $prop;
	}

	/**
	 * Append a mark to the current set unless an equivalent one is already
	 * present. Equivalence is by kind plus structural equality of attrs.
	 *
	 * @param array<int, array<string, mixed>> $marks Current marks.
	 * @param array<string, mixed>             $mark  Mark to add.
	 * @return array<int, array<string, mixed>> Updated mark set.
	 */
	private function push_mark( array $marks, array $mark ): array {
		foreach ( $marks as $existing ) {
			if ( $this->marks_equal( $existing, $mark ) ) {
				return $marks;
			}
		}

		$marks[] = $mark;

		return $marks;
	}

	/**
	 * Structural equality for two mark descriptors: same kind and same attrs.
	 *
	 * @param array<string, mixed> $a First mark.
	 * @param array<string, mixed> $b Second mark.
	 * @return bool True when both are the same mark instance.
	 */
	private function marks_equal( array $a, array $b ): bool {
		if ( ( $a['kind'] ?? null ) !== ( $b['kind'] ?? null ) ) {
			return false;
		}

		$attrs_a = $a['attrs'] ?? array();
		$attrs_b = $b['attrs'] ?? array();

		return $attrs_a === $attrs_b;
	}

	/**
	 * Drop pure-whitespace leaves from the start and end of the list.
	 *
	 * Whitespace between inline siblings is preserved verbatim — only the
	 * outermost framing whitespace (e.g. the indentation around an element on
	 * its own line) is removed.
	 *
	 * @param array<int, array<string, mixed>> $leaves Leaves to trim.
	 * @return array<int, array<string, mixed>> Trimmed leaves.
	 */
	private function trim_outer_whitespace( array $leaves ): array {
		while ( ! empty( $leaves ) && '' === trim( $leaves[0]['text'] ) ) {
			array_shift( $leaves );
		}

		$last_index = count( $leaves ) - 1;
		while ( $last_index >= 0 && '' === trim( $leaves[ $last_index ]['text'] ) ) {
			array_pop( $leaves );
			--$last_index;
		}

		return $leaves;
	}

	/**
	 * Sort a leaf's mark set by canonical precedence (link > strong > em > mark).
	 *
	 * Within a single leaf there can never be two marks of the same kind, so
	 * the precedence map is sufficient to fully order the set.
	 *
	 * @param array<int, array<string, mixed>> $marks Marks to order.
	 * @return array<int, array<string, mixed>> Marks in canonical order.
	 */
	private function order_marks( array $marks ): array {
		$by_kind = array();
		foreach ( $marks as $mark ) {
			$kind             = $mark['kind'];
			$by_kind[ $kind ] = $mark;
		}

		$ordered = array();
		foreach ( self::MARK_PRECEDENCE as $kind ) {
			if ( isset( $by_kind[ $kind ] ) ) {
				$ordered[] = $by_kind[ $kind ];
			}
		}

		return $ordered;
	}

	/**
	 * Pass 2 — recursively fold a flat list of leaves into a canonical tree.
	 *
	 * At each depth, consecutive leaves that share the mark at $depth (kind +
	 * attrs equal) are grouped under a single wrapper node. Leaves that have
	 * no mark at this depth are emitted as text nodes inline with their
	 * surrounding wrappers.
	 *
	 * @param array<int, array<string, mixed>> $leaves Leaves with marks already
	 *                                                 ordered by precedence.
	 * @param int                              $depth  How many marks have been
	 *                                                 peeled off so far.
	 * @return array<int, array<string, mixed>> Canonical AST nodes.
	 */
	private function fold_leaves_at_depth( array $leaves, int $depth ): array {
		$result = array();
		$total  = count( $leaves );
		$index  = 0;

		while ( $index < $total ) {
			$leaf  = $leaves[ $index ];
			$marks = $leaf['marks'];

			if ( $depth >= count( $marks ) ) {
				$result[] = array(
					'type' => 'text',
					'text' => $leaf['text'],
				);
				++$index;
				continue;
			}

			$current_mark = $marks[ $depth ];
			$group        = array( $leaf );
			$lookahead    = $index + 1;

			while ( $lookahead < $total ) {
				$next_marks = $leaves[ $lookahead ]['marks'];
				if ( $depth >= count( $next_marks ) ) {
					break;
				}
				if ( ! $this->marks_equal( $next_marks[ $depth ], $current_mark ) ) {
					break;
				}
				$group[] = $leaves[ $lookahead ];
				++$lookahead;
			}

			$wrapper             = $this->build_wrapper_node( $current_mark );
			$wrapper['children'] = $this->fold_leaves_at_depth( $group, $depth + 1 );
			$result[]            = $wrapper;
			$index               = $lookahead;
		}

		return $result;
	}

	/**
	 * Build an empty AST wrapper node for a mark.
	 *
	 * @param array<string, mixed> $mark Mark descriptor with 'kind' and optional 'attrs'.
	 * @return array<string, mixed> Wrapper node ready for children to be appended.
	 */
	private function build_wrapper_node( array $mark ): array {
		if ( isset( $mark['attrs'] ) ) {
			return array(
				'type'     => $mark['kind'],
				'attrs'    => $mark['attrs'],
				'children' => array(),
			);
		}

		return array(
			'type'     => $mark['kind'],
			'children' => array(),
		);
	}
}
