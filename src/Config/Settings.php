<?php
/**
 * Plugin settings.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

namespace WSO\PopularProducts\Config;

/**
 * Typed access to the single `wso_popular_products` option.
 */
final class Settings {

	public const OPTION = 'wso_popular_products';

	/**
	 * Currencies the storefront may show a price in, besides the store currency.
	 *
	 * @var string[]
	 */
	public const CURRENCIES = array( 'USD', 'EUR', 'PLN' );

	/**
	 * Stored settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$known  = array_intersect_key( $stored, self::defaults() );

		return array_merge( self::defaults(), $known );
	}

	/**
	 * Default values, also used as the Settings API default.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			// A kill switch rather than an opt-in: blocks already decide per instance,
			// so the site-wide value only ever removes the feature.
			'currency_enabled' => true,
		);
	}

	/**
	 * Whether the currency switcher may appear anywhere on the site.
	 */
	public static function currencyEnabled(): bool {
		return (bool) ( self::all()['currency_enabled'] ?? true );
	}

	/**
	 * Currencies offered to the shopper, base currency excluded.
	 *
	 * @return string[]
	 */
	public static function currencies(): array {
		return self::CURRENCIES;
	}

	/**
	 * Keeps the class uninstantiable.
	 */
	private function __construct() {}
}
