<?php
/**
 * Block registration.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

namespace WSO\PopularProducts\Block;

use WSO\PopularProducts\Contract\HookRegistrar;

/**
 * Registers every block shipped by this plugin from its block.json.
 */
final class BlockRegistrar implements HookRegistrar {

	/**
	 * Block directories, relative to the plugin root.
	 *
	 * @var string[]
	 */
	private const BLOCKS = array(
		'blocks/popular-products',
		'blocks/nbu-rates',
	);

	/**
	 * Sets up the registrar.
	 *
	 * @param string $pluginDir Absolute path to the plugin directory.
	 */
	public function __construct( private readonly string $pluginDir ) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$this->registerBlocks();
	}

	/**
	 * Registers every block in BLOCKS.
	 */
	public function registerBlocks(): void {
		foreach ( self::BLOCKS as $relative ) {
			$metadata = $this->pluginDir . '/' . $relative . '/block.json';

			if ( is_readable( $metadata ) ) {
				register_block_type( $metadata );
			}
		}
	}

	/**
	 * The block directories this plugin registers.
	 *
	 * @return string[]
	 */
	public static function blocks(): array {
		return self::BLOCKS;
	}
}
