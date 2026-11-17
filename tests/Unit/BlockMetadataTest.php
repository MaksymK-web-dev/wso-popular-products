<?php
/**
 * Block metadata tests.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

namespace WSO\PopularProducts\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WSO\PopularProducts\Block\BlockRegistrar;

/**
 * Guards the contract in every block.json this plugin ships.
 */
final class BlockMetadataTest extends TestCase {

	/**
	 * Plugin root.
	 */
	private static function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Relative block directories the plugin registers.
	 *
	 * @return array<string, array{string}> Block directory per test.
	 */
	public static function blocks(): array {
		$cases = array();

		foreach ( BlockRegistrar::blocks() as $relative ) {
			$cases[ $relative ] = array( $relative );
		}

		return $cases;
	}

	/**
	 * Decoded metadata for one block.
	 *
	 * @param string $relative Block directory.
	 * @return array<string, mixed>
	 */
	private static function meta( string $relative ): array {
		$path = self::root() . '/' . $relative . '/block.json';

		self::assertFileExists( $path, "{$relative}/block.json is missing." );

		$decoded = json_decode( (string) file_get_contents( $path ), true );
		self::assertIsArray( $decoded, "{$relative}/block.json is not valid JSON." );

		return $decoded;
	}

	/**
	 * Resolves a `file:./name` reference against a block directory.
	 *
	 * @param string $relative  Block directory.
	 * @param string $reference Metadata reference.
	 */
	private static function resolve( string $relative, string $reference ): string {
		return self::root() . '/' . $relative . '/' . ltrim( str_replace( 'file:', '', $reference ), './' );
	}

	/**
	 * Every registered block has readable metadata with a vendor-prefixed name.
	 *
	 * @param string $relative Block directory.
	 */
	#[DataProvider( 'blocks' )]
	public function testBlockNameIsPrefixed( string $relative ): void {
		$meta = self::meta( $relative );

		self::assertMatchesRegularExpression( '/^wso\/[a-z-]+$/', (string) ( $meta['name'] ?? '' ) );
		self::assertSame( 3, $meta['apiVersion'] ?? null, "{$relative} must use apiVersion 3." );
		self::assertFalse( (bool) ( $meta['supports']['html'] ?? true ), "{$relative} must refuse custom HTML." );
	}

	/**
	 * A dynamic block declares a render file, and the file exists.
	 *
	 * @param string $relative Block directory.
	 */
	#[DataProvider( 'blocks' )]
	public function testRenderFileExists( string $relative ): void {
		$meta = self::meta( $relative );

		self::assertArrayHasKey( 'render', $meta, "{$relative} is not a dynamic block." );
		self::assertFileExists( self::resolve( $relative, (string) $meta['render'] ) );
	}

	/**
	 * Every `file:` reference points at a real file.
	 *
	 * @param string $relative Block directory.
	 */
	#[DataProvider( 'blocks' )]
	public function testReferencedFilesExist( string $relative ): void {
		$meta = self::meta( $relative );

		foreach ( array( 'editorScript', 'editorStyle', 'style', 'viewScriptModule' ) as $key ) {
			if ( ! isset( $meta[ $key ] ) ) {
				continue;
			}

			$value = (string) $meta[ $key ];

			self::assertStringStartsWith( 'file:', $value, "{$relative}: {$key} must use the file: convention." );
			self::assertFileExists( self::resolve( $relative, $value ), "{$relative}: {$key} points at a missing file." );
		}
	}

	/**
	 * A missing dependency here breaks the import map in the browser.
	 *
	 * @param string $relative Block directory.
	 */
	#[DataProvider( 'blocks' )]
	public function testViewModuleDeclaresInteractivity( string $relative ): void {
		$meta = self::meta( $relative );

		if ( ! isset( $meta['viewScriptModule'] ) ) {
			self::markTestSkipped( "{$relative} has no view module." );
		}

		$asset = self::root() . '/' . $relative . '/view.asset.php';
		self::assertFileExists( $asset );

		$data = require $asset;

		self::assertIsArray( $data );
		self::assertContains( '@wordpress/interactivity', $data['dependencies'] ?? array() );
	}

	/**
	 * Without edit.asset.php the block does not reach the inserter.
	 *
	 * @param string $relative Block directory.
	 */
	#[DataProvider( 'blocks' )]
	public function testEditorAssetDeclaresCoreDependencies( string $relative ): void {
		$asset = self::root() . '/' . $relative . '/edit.asset.php';
		self::assertFileExists( $asset, "{$relative}/edit.asset.php is missing." );

		$data = require $asset;

		self::assertIsArray( $data );

		foreach ( array( 'wp-blocks', 'wp-element', 'wp-block-editor' ) as $handle ) {
			self::assertContains( $handle, $data['dependencies'] ?? array(), "{$relative} does not depend on {$handle}." );
		}
	}

	/**
	 * The text domain must match the plugin slug, or translations are never found.
	 *
	 * @param string $relative Block directory.
	 */
	#[DataProvider( 'blocks' )]
	public function testTextDomain( string $relative ): void {
		self::assertSame( 'wso-popular-products', self::meta( $relative )['textdomain'] ?? null );
	}

	/**
	 * Every registered block is actually on disk.
	 */
	public function testRegisteredDirectoriesExist(): void {
		self::assertNotEmpty( BlockRegistrar::blocks() );

		foreach ( BlockRegistrar::blocks() as $relative ) {
			self::assertDirectoryExists( self::root() . '/' . $relative );
		}
	}
}
