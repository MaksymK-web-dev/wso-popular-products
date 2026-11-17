<?php
/**
 * Composition root.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

namespace WSO\PopularProducts;

use WSO\PopularProducts\Block\BlockRegistrar;
use WSO\PopularProducts\Rates\RateProvider;
use WSO\PopularProducts\Rates\RatesController;
use WSO\PopularProducts\Rest\ProductsController;

/**
 * Wires the block registration and the REST endpoints.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Whether boot() has run.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Returns the shared instance.
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Registers hooks.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		( new BlockRegistrar( WSO_PP_DIR ) )->register();
		( new ProductsController() )->register();
		( new RatesController( new RateProvider() ) )->register();
	}

	/**
	 * Private constructor: the class is a singleton.
	 */
	private function __construct() {}
}
