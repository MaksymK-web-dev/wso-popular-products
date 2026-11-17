<?php
/**
 * PSR-4 autoloader for the WSO\PopularProducts namespace.
 *
 * No Composer runtime dependency: the plugin must work when dropped into a WordPress
 * install that never ran `composer install`.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'WSO\\PopularProducts\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
