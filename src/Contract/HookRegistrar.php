<?php
/**
 * Hook registrar contract.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

namespace WSO\PopularProducts\Contract;

/**
 * A service that owns a set of WordPress hooks.
 */
interface HookRegistrar {

	/**
	 * Attaches the service to WordPress.
	 */
	public function register(): void;
}
