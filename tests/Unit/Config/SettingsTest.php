<?php
/**
 * Settings tests.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

namespace WSO\PopularProducts\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use WSO\PopularProducts\Config\Settings;

/**
 * Pins the site-wide currency switch: on unless an admin turns it off.
 */
final class SettingsTest extends TestCase {

	/**
	 * Clears the option store.
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wso_pp_test_options'] = array();
	}

	/**
	 * A fresh install offers the switcher; blocks still opt in one by one.
	 */
	public function testCurrencySwitcherIsOnByDefault(): void {
		self::assertTrue( Settings::currencyEnabled() );
	}

	/**
	 * Saving the screen with the box checked keeps it on.
	 */
	public function testStoredTrueKeepsItOn(): void {
		$GLOBALS['wso_pp_test_options'][ Settings::OPTION ] = array( 'currency_enabled' => true );

		self::assertTrue( Settings::currencyEnabled() );
	}

	/**
	 * Saving the screen with the box cleared turns it off everywhere.
	 */
	public function testStoredFalseTurnsItOff(): void {
		$GLOBALS['wso_pp_test_options'][ Settings::OPTION ] = array( 'currency_enabled' => false );

		self::assertFalse( Settings::currencyEnabled() );
	}

	/**
	 * A string from options.php is not a boolean; it must not read as "off".
	 */
	public function testStringValueFromTheFormIsCoerced(): void {
		$GLOBALS['wso_pp_test_options'][ Settings::OPTION ] = array( 'currency_enabled' => '1' );

		self::assertTrue( Settings::currencyEnabled() );
	}

	/**
	 * Garbage in the option cannot enable a hidden feature silently.
	 */
	public function testUnknownKeysAreIgnored(): void {
		$GLOBALS['wso_pp_test_options'][ Settings::OPTION ] = array( 'currency_enabled' => true, 'nonsense' => 'x' );

		self::assertTrue( Settings::currencyEnabled() );
		self::assertSame( array( 'currency_enabled' ), array_keys( Settings::all() ) );
	}

	/**
	 * The offered currencies exclude the store currency, which is always available.
	 */
	public function testOfferedCurrenciesAreIsoCodes(): void {
		self::assertNotEmpty( Settings::currencies() );

		foreach ( Settings::currencies() as $code ) {
			self::assertMatchesRegularExpression( '/^[A-Z]{3}$/', $code );
		}
	}
}
