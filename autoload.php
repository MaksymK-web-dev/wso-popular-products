<?php
/**
 * PSR-4 autoloader.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

// This file registers a handler at load time, so it must not run when requested directly.
// `return` rather than `exit`: it stops direct access but does not kill a process that
// includes the file, such as the test bootstrap.
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'WSO\\PopularProducts\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );

		// A class name becomes a path, so it must not be able to climb out of src/.
		if ( str_contains( $relative, '..' ) ) {
			return;
		}

		$path = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
