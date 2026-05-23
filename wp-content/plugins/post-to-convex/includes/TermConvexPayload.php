<?php
/**
 * WordPress term → Convex API payload shapes.
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
 * Builds request bodies for category and tag Convex sync endpoints.
 */
final class TermConvexPayload {

	/**
	 * Map a WordPress category term to the Convex category shape.
	 *
	 * @param \WP_Term $term Category term.
	 * @return array<string, int|string>
	 */
	public static function category( \WP_Term $term ): array {
		$category = array(
			'originalId'  => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
		);

		if ( (int) $term->parent > 0 ) {
			$category['parentOriginalId'] = (int) $term->parent;
		}

		return $category;
	}

	/**
	 * Map a WordPress tag term to the Convex tag shape.
	 *
	 * @param \WP_Term $term Tag term.
	 * @return array<string, int|string>
	 */
	public static function tag( \WP_Term $term ): array {
		return array(
			'originalId'  => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
		);
	}

	/**
	 * DELETE request body for a term referenced by WordPress term ID.
	 *
	 * @param int $term_id WordPress term ID.
	 * @return array{originalId: int}
	 */
	public static function delete( int $term_id ): array {
		return array(
			'originalId' => $term_id,
		);
	}
}
