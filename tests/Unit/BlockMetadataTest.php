<?php
/**
 * Block metadata tests.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

namespace WSO\PopularProducts\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the contract in block.json.
 *
 * A block is only as good as its metadata: a typo in a file path or a missing asset
 * dependency fails silently at runtime — the block renders, then does nothing. These
 * assertions catch that class of bug without a browser.
 */
final class BlockMetadataTest extends TestCase {

	/**
	 * Decoded block.json.
	 *
	 * @var array<string, mixed>
	 */
	private static array $meta = array();

	/**
	 * Loads the metadata once.
	 */
	public static function setUpBeforeClass(): void {
		$path = dirname( __DIR__, 2 ) . '/block.json';
		self::assertFileExists( $path );

		$decoded    = json_decode( (string) file_get_contents( $path ), true );
		self::$meta = is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * block.json must be valid JSON.
	 */
	public function testMetadataIsValidJson(): void {
		self::assertNotEmpty( self::$meta );
	}

	/**
	 * The block name drives the namespace used in PHP, JS and CSS.
	 */
	public function testBlockName(): void {
		self::assertSame( 'wso/popular-products', self::$meta['name'] ?? null );
	}

	/**
	 * apiVersion 3 is the current metadata API.
	 */
	public function testApiVersion(): void {
		self::assertSame( 3, self::$meta['apiVersion'] ?? null );
	}

	/**
	 * The block is dynamic: it must declare a render file and save nothing into post content.
	 */
	public function testIsDynamic(): void {
		self::assertArrayHasKey( 'render', self::$meta );
		self::assertFileExists( $this->resolve( (string) self::$meta['render'] ) );
	}

	/**
	 * Every file reference must resolve to a real file.
	 *
	 * @param string $key Metadata key.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'scriptKeys' )]
	public function testReferencedFilesExist( string $key ): void {
		if ( ! isset( self::$meta[ $key ] ) ) {
			self::markTestSkipped( "{$key} not declared." );
		}

		$value = (string) self::$meta[ $key ];
		self::assertStringStartsWith( 'file:', $value, "{$key} must use the file: convention." );
		self::assertFileExists( $this->resolve( $value ) );
	}

	/**
	 * Metadata keys that point at files.
	 *
	 * @return array<string, array{string}>
	 */
	public static function scriptKeys(): array {
		return array(
			'editorScript'     => array( 'editorScript' ),
			'viewScriptModule' => array( 'viewScriptModule' ),
			'style'            => array( 'style' ),
			'editorStyle'      => array( 'editorStyle' ),
			'render'           => array( 'render' ),
		);
	}

	/**
	 * The block must be usable without a build step, which means the asset metadata for
	 * the view module has to be written by hand — a missing dependency there silently
	 * breaks the import of @wordpress/interactivity in the browser.
	 */
	public function testViewModuleDeclaresInteractivityDependency(): void {
		$asset = $this->resolve( 'file:./assets/view.asset.php' );
		self::assertFileExists( $asset );

		$data = require $asset;

		self::assertIsArray( $data );
		self::assertContains(
			'@wordpress/interactivity',
			$data['dependencies'] ?? array(),
			'assets/view.asset.php must declare @wordpress/interactivity.'
		);
	}

	/**
	 * Attribute defaults are the block's documented behaviour; the editor preview and the
	 * render callback both rely on them.
	 */
	public function testAttributeDefaults(): void {
		$attributes = self::$meta['attributes'] ?? array();

		self::assertSame( 'Popular products', $attributes['heading']['default'] ?? null );
		self::assertSame( 3, $attributes['limit']['default'] ?? null );
		self::assertTrue( $attributes['showPrice']['default'] ?? null );
		self::assertTrue( $attributes['showImage']['default'] ?? null );
	}

	/**
	 * The render callback escapes, so the block must refuse custom HTML.
	 */
	public function testHtmlSupportIsDisabled(): void {
		self::assertFalse( self::$meta['supports']['html'] ?? true );
	}

	/**
	 * Resolves a `file:./path` reference against the plugin root.
	 *
	 * @param string $reference Metadata reference.
	 */
	private function resolve( string $reference ): string {
		return dirname( __DIR__, 2 ) . '/' . ltrim( str_replace( 'file:', '', $reference ), './' );
	}
}
