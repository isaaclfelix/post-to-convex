<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName -- PHPCS sniff file naming convention.
/**
 * Requires a typed @var on class constants when no native type hint is present.
 *
 * Slevomat ClassConstantTypeHint only enforces native types on PHP 8.3+ and treats
 * redundant var annotations as removable; this sniff fills the gap on PHP 8.2.
 *
 * @package TypeSafety
 */

declare( strict_types=1 );

namespace TypeSafety\Sniffs\TypeHints;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\AnnotationHelper;
use SlevomatCodingStandard\Helpers\ClassHelper;
use SlevomatCodingStandard\Helpers\TokenHelper;
use const T_CONST;
use const T_EQUAL;

/**
 * Ensures class constants declare a type via native hint or @var.
 */
class ClassConstantVarAnnotationSniff implements Sniff {

	/**
	 * Registers tokens to listen for.
	 *
	 * @return array<int, int|string>
	 *
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
	 */
	public function register() {
		return array( T_CONST );
	}

	/**
	 * Processes a class constant declaration.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 *
	 * @return void
	 *
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
	 */
	public function process( File $phpcs_file, $stack_ptr ) {
		if ( null === ClassHelper::getClassPointer( $phpcs_file, $stack_ptr ) ) {
			return;
		}

		if ( $this->has_native_type_hint( $phpcs_file, $stack_ptr ) ) {
			return;
		}

		$annotations = AnnotationHelper::getAnnotations( $phpcs_file, $stack_ptr, '@var' );
		if ( array() !== $annotations ) {
			return;
		}

		$name_pointer = $this->get_constant_name_pointer( $phpcs_file, $stack_ptr );
		$tokens       = $phpcs_file->getTokens();
		$constant     = $tokens[ $name_pointer ]['content'];

		$phpcs_file->addError(
			sprintf(
				'Class constant %s must have a typed @var annotation (or a native type hint on PHP 8.3+).',
				$constant
			),
			$stack_ptr,
			'MissingVarAnnotation'
		);
	}

	/**
	 * Whether the constant uses a native type hint.
	 *
	 * @param File $phpcs_file      The file being scanned.
	 * @param int  $constant_pointer Position of the const keyword.
	 * @return bool
	 */
	private function has_native_type_hint( File $phpcs_file, int $constant_pointer ): bool {
		$name_pointer    = $this->get_constant_name_pointer( $phpcs_file, $constant_pointer );
		$type_hint_token = TokenHelper::findPreviousEffective( $phpcs_file, $name_pointer - 1 );

		return $type_hint_token !== $constant_pointer;
	}

	/**
	 * Returns the pointer to the constant name token.
	 *
	 * @param File $phpcs_file      The file being scanned.
	 * @param int  $constant_pointer Position of the const keyword.
	 * @return int
	 */
	private function get_constant_name_pointer( File $phpcs_file, int $constant_pointer ): int {
		$equal_pointer = TokenHelper::findNext( $phpcs_file, T_EQUAL, $constant_pointer + 1 );

		return TokenHelper::findPreviousEffective( $phpcs_file, $equal_pointer - 1 );
	}
}
