<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName -- PHPCS sniff file naming convention.
/**
 * Enforces PostToConvex namespace and PSR-4 class/file name alignment in includes/.
 *
 * @package PostToConvexPHPCS
 */

namespace PostToConvexPHPCS\Sniffs\Includes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Requires namespace PostToConvex and a single class whose name matches the file basename.
 */
class Psr4ClassSniff implements Sniff {

	/**
	 * Required namespace for all files in includes/.
	 */
	private const REQUIRED_NAMESPACE = 'PostToConvex';

	/**
	 * Returns the token types that this sniff wants to listen for.
	 *
	 * @return array<int, int|string>
	 */
	public function register() {
		return array( T_OPEN_TAG );
	}

	/**
	 * Processes this sniff, when one of its tokens is encountered.
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int  $stack_ptr  The position of the current token in the stack.
	 * @return void
	 */
	public function process( File $phpcs_file, $stack_ptr ) {
		$path = $phpcs_file->getFilename();

		if ( ! $this->is_includes_file( $path ) ) {
			return;
		}

		$first_open_tag = $phpcs_file->findNext( T_OPEN_TAG, 0 );
		if ( $stack_ptr !== $first_open_tag ) {
			return;
		}

		$filename  = basename( $path, '.php' );
		$namespace = $phpcs_file->findNext( T_NAMESPACE, $stack_ptr );

		if ( false === $namespace ) {
			$phpcs_file->addError(
				sprintf( 'All files in includes/ must declare namespace %s.', self::REQUIRED_NAMESPACE ),
				$stack_ptr,
				'NamespaceMissing'
			);
			return;
		}

		$declared_namespace = $this->get_namespace_name( $phpcs_file, $namespace );

		if ( null === $declared_namespace ) {
			$phpcs_file->addError(
				sprintf( 'Use a semicolon-terminated namespace declaration for %s.', self::REQUIRED_NAMESPACE ),
				$namespace,
				'NamespaceBracketed'
			);
			return;
		}

		if ( self::REQUIRED_NAMESPACE !== $declared_namespace ) {
			$phpcs_file->addError(
				sprintf(
					'Namespace must be %1$s, found %2$s.',
					self::REQUIRED_NAMESPACE,
					$declared_namespace ? $declared_namespace : '(none)'
				),
				$namespace,
				'NamespaceInvalid'
			);
		}

		$classes = $this->get_named_classes( $phpcs_file );

		if ( array() === $classes ) {
			$phpcs_file->addError(
				'Each file in includes/ must declare exactly one class.',
				$stack_ptr,
				'ClassMissing'
			);
			return;
		}

		if ( count( $classes ) > 1 ) {
			$phpcs_file->addError(
				'Only one class is allowed per file in includes/.',
				$classes[1]['ptr'],
				'MultipleClasses'
			);
			return;
		}

		$class = $classes[0];

		if ( $class['name'] !== $filename ) {
			$phpcs_file->addError(
				sprintf(
					'Class name "%1$s" must match file name "%2$s.php" (PSR-4).',
					$class['name'],
					$filename
				),
				$class['ptr'],
				'ClassNameMismatch'
			);
		}
	}

	/**
	 * Whether the file is under the plugin includes directory.
	 *
	 * @param string $path Absolute or relative file path.
	 * @return bool
	 */
	private function is_includes_file( $path ) {
		$normalized = str_replace( '\\', '/', $path );

		return (bool) preg_match(
			'#(?:^|/)wp-content/plugins/post-to-convex/includes/[^/]+\.php$#',
			$normalized
		);
	}

	/**
	 * Read the declared namespace name following a T_NAMESPACE token.
	 *
	 * @param File $phpcs_file      The file being scanned.
	 * @param int  $namespace_ptr Position of the namespace keyword.
	 * @return string|null Namespace name or null when bracketed syntax is used.
	 */
	private function get_namespace_name( File $phpcs_file, $namespace_ptr ) {
		$tokens = $phpcs_file->getTokens();
		$ptr    = $phpcs_file->findNext( T_WHITESPACE, $namespace_ptr + 1, null, true );

		if ( false === $ptr ) {
			return null;
		}

		if ( T_OPEN_CURLY_BRACKET === $tokens[ $ptr ]['code'] ) {
			return null;
		}

		$parts = array();

		while ( false !== $ptr && in_array( $tokens[ $ptr ]['code'], array( T_STRING, T_NS_SEPARATOR ), true ) ) {
			if ( T_STRING === $tokens[ $ptr ]['code'] ) {
				$parts[] = $tokens[ $ptr ]['content'];
			}
			++$ptr;
		}

		return implode( '\\', $parts );
	}

	/**
	 * Collect named class declarations (excludes anonymous classes).
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @return array<int, array{ptr: int, name: string}>
	 */
	private function get_named_classes( File $phpcs_file ) {
		$tokens  = $phpcs_file->getTokens();
		$classes = array();
		$search  = 0;

		$class_ptr = $phpcs_file->findNext( T_CLASS, $search );
		while ( false !== $class_ptr ) {
			$search = $class_ptr + 1;

			$name_ptr = $phpcs_file->findNext( T_WHITESPACE, $class_ptr + 1, null, true );

			if ( false === $name_ptr || T_STRING !== $tokens[ $name_ptr ]['code'] ) {
				$class_ptr = $phpcs_file->findNext( T_CLASS, $search );
				continue;
			}

			$classes[] = array(
				'ptr'  => $class_ptr,
				'name' => $tokens[ $name_ptr ]['content'],
			);

			$class_ptr = $phpcs_file->findNext( T_CLASS, $search );
		}

		return $classes;
	}
}
