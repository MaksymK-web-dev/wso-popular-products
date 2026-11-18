<?php
/**
 * PHPUnit bootstrap.
 *
 * Stubs the few WordPress functions the covered classes call.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

// The plugin files refuse to load outside WordPress; the suite emulates it.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoload.php';

/**
 * In-memory option store. Tests reset it in setUp().
 *
 * @var array<string, mixed>
 */
$GLOBALS['wso_pp_test_options'] = array();

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Option read stub.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default Value when the option is absent.
	 * @return mixed
	 */
	function get_option( string $name, mixed $default = false ): mixed {
		return $GLOBALS['wso_pp_test_options'][ $name ] ?? $default;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation stub: returns the source string unchanged.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain (ignored).
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
