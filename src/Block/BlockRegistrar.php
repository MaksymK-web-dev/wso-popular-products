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
 * Registers the block from block.json.
 *
 * The block.json file is the single source of truth: scripts, styles, attributes and the
 * render file are declared there, so the PHP side stays at four lines and the metadata is
 * readable by tooling.
 *
 * Registration happens immediately rather than on a second `init` hook. The plugin boots
 * on `init` itself, so adding another `init` callback from inside that hook is a race —
 * the first version of this class did exactly that and the block silently never appeared.
 */
final class BlockRegistrar implements HookRegistrar {

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
		$this->registerBlock();
	}

	/**
	 * Registers the block type.
	 */
	public function registerBlock(): void {
		register_block_type( $this->pluginDir . '/block.json' );
	}
}
