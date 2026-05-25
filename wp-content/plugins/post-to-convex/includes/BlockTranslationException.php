<?php
/**
 * Raised when a Gutenberg block cannot be translated for Convex post sync.
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
 * User-facing failure while translating post content blocks.
 */
class BlockTranslationException extends \Exception {

}
