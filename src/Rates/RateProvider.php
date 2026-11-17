<?php
/**
 * Official NBU exchange rates.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

namespace WSO\PopularProducts\Rates;

use WSO\PopularProducts\Support\Logger;

/**
 * Reads the National Bank of Ukraine daily rates and caches them.
 *
 * Why a server-side proxy when bank.gov.ua sends `Access-Control-Allow-Origin: *` and the
 * browser could call it directly:
 *
 *   1. One cache instead of one request per visitor — the NBU endpoint returns ~5 KB of
 *      XML-derived JSON for every currency, which no single page needs in full.
 *   2. The page keeps working when the bank's API is slow or down: the last good answer
 *      is kept separately and served stale rather than showing an error.
 *   3. The response shape is ours, so the block never depends on NBU's field names.
 *
 * The trade-off is a second HTTP hop, paid once per cache period.
 */
final class RateProvider {

	/**
	 * Daily rates endpoint. `?json` switches the response from XML to JSON.
	 */
	private const ENDPOINT = 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?json';

	/**
	 * Cache key for the current answer.
	 */
	private const CACHE_KEY = 'wso_nbu_rates';

	/**
	 * Cache lifetime: the NBU publishes once per working day, so six hours is generous
	 * without being stale for long.
	 */
	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Cache key for the last successful answer, kept without expiry.
	 */
	private const LAST_GOOD_KEY = 'wso_nbu_rates_last_good';

	/**
	 * Currencies that make sense in a Ukrainian store's price list.
	 *
	 * The endpoint returns ~40 entries; the rest are only noise on a product card.
	 *
	 * @var string[]
	 */
	public const COMMON_CODES = array( 'USD', 'EUR', 'PLN', 'GBP', 'CHF', 'CZK' );

	/**
	 * Returns the rate table.
	 *
	 * Shape:
	 *   [
	 *     'ok'        => bool,
	 *     'base'      => 'UAH',
	 *     'date'      => '18.11.2026' | '',
	 *     'fetchedAt' => int,          // Unix time this answer was retrieved
	 *     'stale'     => bool,         // true when served from the last-good copy
	 *     'error'     => string,       // empty when ok
	 *     'rates'     => [ 'USD' => ['code'=>'USD','name'=>'Долар США','rate'=>45.3436], … ],
	 *   ]
	 *
	 * @return array<string, mixed>
	 */
	public function table(): array {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) && isset( $cached['rates'] ) ) {
			return $cached;
		}

		$fresh = $this->fetch();

		if ( $fresh['ok'] ) {
			set_transient( self::CACHE_KEY, $fresh, self::CACHE_TTL );
			// Autoload is off: this copy is only read when a fetch fails.
			update_option( self::LAST_GOOD_KEY, $fresh, false );

			return $fresh;
		}

		$last_good = get_option( self::LAST_GOOD_KEY );

		if ( is_array( $last_good ) && ! empty( $last_good['rates'] ) ) {
			$last_good['stale'] = true;
			Logger::warning( 'NBU rates unavailable, serving the last good answer: ' . $fresh['error'] );

			return $last_good;
		}

		Logger::warning( 'NBU rates unavailable and no cached answer exists: ' . $fresh['error'] );

		return $fresh;
	}

	/**
	 * Returns one currency's rate, or null when it is not in the table.
	 *
	 * @param string $code ISO 4217 code, e.g. USD.
	 */
	public function rate( string $code ): ?float {
		$table = $this->table();
		$code  = self::normalizeCode( $code );

		if ( '' === $code || ! isset( $table['rates'][ $code ]['rate'] ) ) {
			return null;
		}

		return (float) $table['rates'][ $code ]['rate'];
	}

	/**
	 * Normalizes a currency code to three upper-case letters.
	 *
	 * @param string $code Raw code.
	 */
	public static function normalizeCode( string $code ): string {
		$code = strtoupper( trim( $code ) );

		return 1 === preg_match( '/^[A-Z]{3}$/', $code ) ? $code : '';
	}

	/**
	 * Parses a comma-separated list of codes, dropping anything invalid.
	 *
	 * @param string $codes_raw Raw list, e.g. "usd, eur ,XX".
	 * @return string[]
	 */
	public static function parseCodes( string $codes_raw ): array {
		$codes = array();

		foreach ( explode( ',', $codes_raw ) as $candidate ) {
			$code = self::normalizeCode( $candidate );

			if ( '' !== $code ) {
				$codes[ $code ] = $code;
			}
		}

		return array_values( $codes );
	}

	/**
	 * Empty result used for failures.
	 *
	 * @param string $error Diagnostic message.
	 * @return array<string, mixed>
	 */
	private function failure( string $error ): array {
		return array(
			'ok'        => false,
			'base'      => 'UAH',
			'date'      => '',
			'fetchedAt' => time(),
			'stale'     => false,
			'error'     => $error,
			'rates'     => array(),
		);
	}

	/**
	 * Calls the NBU endpoint and normalizes the answer.
	 *
	 * @return array<string, mixed>
	 */
	private function fetch(): array {
		$response = wp_remote_get(
			self::ENDPOINT,
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->failure( $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status ) {
			return $this->failure( sprintf( 'NBU responded with HTTP %d', $status ) );
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) ) {
			return $this->failure( 'NBU response is not a JSON array' );
		}

		$rates = array();
		$date  = '';

		foreach ( $decoded as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['cc'], $row['rate'] ) ) {
				continue;
			}

			$code = self::normalizeCode( (string) $row['cc'] );
			$rate = (float) $row['rate'];

			// A zero or negative rate would make every conversion invalid.
			if ( '' === $code || $rate <= 0 ) {
				continue;
			}

			$rates[ $code ] = array(
				'code' => $code,
				'name' => sanitize_text_field( (string) ( $row['txt'] ?? $code ) ),
				'rate' => $rate,
			);

			if ( '' === $date && isset( $row['exchangedate'] ) && is_string( $row['exchangedate'] ) ) {
				$date = self::normalizeDate( $row['exchangedate'] );
			}
		}

		if ( empty( $rates ) ) {
			return $this->failure( 'NBU response contained no usable rates' );
		}

		ksort( $rates );

		return array(
			'ok'        => true,
			'base'      => 'UAH',
			'date'      => $date,
			'fetchedAt' => time(),
			'stale'     => false,
			'error'     => '',
			'rates'     => $rates,
		);
	}

	/**
	 * Accepts NBU's dd.mm.yyyy and returns it, or an empty string when it is malformed.
	 *
	 * @param string $date Raw date.
	 */
	private static function normalizeDate( string $date ): string {
		$date = trim( $date );

		return 1 === preg_match( '/^\d{2}\.\d{2}\.\d{4}$/', $date ) ? $date : '';
	}
}
