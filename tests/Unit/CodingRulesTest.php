<?php
/**
 * Coding-rule tests.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

namespace WSO\PopularProducts\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

/**
 * Pins the house rules so they cannot drift back in.
 *
 * These are not style preferences that a linter is expected to catch — several of them
 * are legal PHP that phpcs will happily pass:
 *
 *   - a short open tag `<?` works or not depending on the server's ini setting;
 *   - `defined() || exit` is a control structure written as an expression;
 *   - `eval()` and `die()` are legal and occasionally ship by accident;
 *   - a conditional inside a template is normal PHP and produces no warning.
 *
 * The render callback is scanned separately: it is allowed to contain `?>`, because a
 * block template is PHP and HTML by design, but it must contain no branching — every
 * decision belongs above the first `?>`.
 */
final class CodingRulesTest extends TestCase {

	/**
	 * Project root.
	 */
	private static function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Our PHP files: src/, the plugin bootstrap, render.php, autoload.php.
	 *
	 * @return array<string, string> Path => contents.
	 */
	private static function sources(): array {
		$files = array();

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( self::root(), FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$path = $file->getPathname();

			if ( str_contains( $path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR ) ) {
				continue;
			}

			if ( str_contains( $path, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR ) ) {
				continue;
			}

			$files[ $path ] = (string) file_get_contents( $path );
		}

		return $files;
	}

	/**
	 * The ABSPATH guard is the one legitimate `exit`.
	 *
	 * It is removed before any rule is applied, so the rules describe the rest of the file
	 * rather than forcing every entry point to look the same.
	 *
	 * @param string $source File contents.
	 * @return string Contents with the guard stripped.
	 */
	private static function stripAbspathGuard( string $source ): string {
		$stripped = preg_replace(
			'/if\s*\(\s*!\s*defined\(\s*[\'\"]ABSPATH[\'\"]\s*\)\s*\)\s*\{\s*exit;\s*\}/s',
			'',
			$source
		);

		return is_string( $stripped ) ? $stripped : $source;
	}

	/**
	 * Short echo tags are forbidden: `<?=` depends on ini settings on some hosts.
	 */
	public function testNoShortEchoTags(): void {
		foreach ( self::sources() as $path => $source ) {
			self::assertStringNotContainsString( '<?=', $source, basename( $path ) . ' uses a short echo tag.' );
		}
	}

	/**
	 * Short open tags are forbidden for the same reason.
	 */
	public function testNoShortOpenTags(): void {
		foreach ( self::sources() as $path => $source ) {
			$count = preg_match_all( '/<\?(?!php|=xml)/', $source );

			self::assertSame( 0, $count, basename( $path ) . ' uses a short open tag.' );
		}
	}

	/**
	 * eval() executes arbitrary code and has no place in a block.
	 */
	public function testNoEval(): void {
		foreach ( self::sources() as $path => $source ) {
			$count = preg_match_all( '/\beval\s*\(/', $source );

			self::assertSame( 0, $count, basename( $path ) . ' calls eval().' );
		}
	}

	/**
	 * die() and exit() terminate the request without a response object.
	 *
	 * The only exception is the ABSPATH guard, which is written as a braced `if` and is
	 * therefore not matched by this pattern.
	 */
	public function testNoDieOrExit(): void {
		foreach ( self::sources() as $path => $source ) {
			$source = self::stripAbspathGuard( $source );
			$count  = preg_match_all( '/\b(die|exit)\s*(\(|;)/', $source );

			self::assertSame( 0, $count, basename( $path ) . ' calls die() or exit().' );
		}
	}

	/**
	 * Control flow must be a statement, not a short-circuited expression.
	 */
	public function testNoShortCircuitControlFlow(): void {
		foreach ( self::sources() as $path => $source ) {
			$count = preg_match_all( '/(\|\||&&)\s*(exit|return|throw|wp_die|die)\b/', $source );

			self::assertSame( 0, $count, basename( $path ) . ' uses a short-circuit as control flow.' );
		}
	}

	/**
	 * No debug helpers left behind.
	 */
	public function testNoDebugHelpers(): void {
		foreach ( self::sources() as $path => $source ) {
			$count = preg_match_all( '/\b(var_dump|print_r|error_log)\s*\(/', $source );

			self::assertSame( 0, $count, basename( $path ) . ' contains a debug helper.' );
		}
	}

	/**
	 * Weak hashes are not a security boundary, and nothing here needs them.
	 */
	public function testNoWeakHashes(): void {
		foreach ( self::sources() as $path => $source ) {
			$count = preg_match_all( '/\b(md5|sha1)\s*\(/', $source );

			self::assertSame( 0, $count, basename( $path ) . ' uses a weak hash.' );
		}
	}

	/**
	 * Variable variables and extract() make data flow impossible to follow.
	 */
	public function testNoDynamicVariableTricks(): void {
		foreach ( self::sources() as $path => $source ) {
			self::assertSame( 0, preg_match_all( '/\$\$[a-zA-Z_]/', $source ), basename( $path ) . ' uses variable variables.' );
			self::assertSame( 0, preg_match_all( '/\bextract\s*\(/', $source ), basename( $path ) . ' calls extract().' );
		}
	}

	/**
	 * Scope: this file defines the rule that has no linter equivalent.
	 */
	public function testSourcesWereActuallyFound(): void {
		self::assertNotEmpty( self::sources(), 'The scan found no files — the test would pass vacuously.' );
	}

	/**
	 * Data files that `return` a value must not contain `exit`.
	 *
	 * This is not style. `*.asset.php` is pulled in with `require`, and an `exit` guard in
	 * one of them terminates the *including* process — which silently truncated this very
	 * suite to seven tests with exit code 0 before the guard was removed.
	 */
	public function testDataFilesDoNotExit(): void {
		$checked = 0;

		foreach ( self::sources() as $path => $source ) {
			if ( ! str_ends_with( $path, '.asset.php' ) ) {
				continue;
			}

			$checked++;

			self::assertSame(
				0,
				preg_match_all( '/\b(die|exit)\s*(\(|;)/', $source ),
				basename( $path ) . ' exits; a required data file must return instead.'
			);
		}

		self::assertGreaterThan( 0, $checked, 'No .asset.php files were found to check.' );
	}

	/**
	 * Every render callback must decide everything before it starts printing.
	 */
	public function testRenderTemplatesContainNoLogic(): void {
		$checked = 0;

		foreach ( glob( self::root() . '/blocks/*/render.php' ) ?: array() as $path ) {
			$checked++;

			$source = (string) file_get_contents( $path );
			$markup = substr( $source, (int) strpos( $source, '?>' ) );
			$name   = basename( dirname( $path ) ) . '/render.php';

			self::assertSame(
				0,
				preg_match_all( '/<\?php\s+(if|else|elseif|foreach|for|while|switch)\b/', $markup ),
				$name . ' branches inside the markup; move the decision above the first ?>.'
			);

			self::assertSame(
				0,
				preg_match_all( '/<\?php\s+echo\s+[^;]*\?[^:]*:/', $markup ),
				$name . ' uses a ternary inside the markup.'
			);
		}

		self::assertGreaterThan( 0, $checked, 'No block render.php files were found to check.' );
	}
}
