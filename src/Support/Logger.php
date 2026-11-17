<?php
/**
 * Plugin logger.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

namespace WSO\PopularProducts\Support;

/**
 * Writes to the WooCommerce log so entries land in WooCommerce → Status → Logs.
 *
 * WooCommerce is a soft dependency of this plugin — the blocks render, they just have no
 * products without it — so a missing logger is a silent no-op rather than an error path.
 * Every call site here is diagnostic, never control flow.
 */
final class Logger {

	/**
	 * Log source name, shown as a separate file in the WooCommerce log viewer.
	 */
	private const SOURCE = 'wso-popular-products';

	/**
	 * Debug-level entry.
	 *
	 * @param string $message Message.
	 */
	public static function debug( string $message ): void {
		self::log( 'debug', $message );
	}

	/**
	 * Warning-level entry.
	 *
	 * @param string $message Message.
	 */
	public static function warning( string $message ): void {
		self::log( 'warning', $message );
	}

	/**
	 * Error-level entry.
	 *
	 * @param string $message Message.
	 */
	public static function error( string $message ): void {
		self::log( 'error', $message );
	}

	/**
	 * Dispatches to wc_get_logger() when it exists.
	 *
	 * @param string $level   PSR-3 level.
	 * @param string $message Message.
	 */
	private static function log( string $level, string $message ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		wc_get_logger()->log( $level, $message, array( 'source' => self::SOURCE ) );
	}

	/**
	 * Keeps the class uninstantiable.
	 */
	private function __construct() {}
}
