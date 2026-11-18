<?php
/**
 * Admin settings screen.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

namespace WSO\PopularProducts\Admin;

use WSO\PopularProducts\Config\Settings;
use WSO\PopularProducts\Contract\HookRegistrar;

/**
 * WooCommerce → Popular Products: the site-wide switches for the blocks.
 */
final class SettingsPage implements HookRegistrar {

	/**
	 * Menu slug, also the Settings API option group.
	 */
	private const SLUG = 'wso-popular-products';

	/**
	 * Capability required to view and save the settings.
	 */
	private const CAPABILITY = 'manage_woocommerce';

	/**
	 * Settings section id.
	 */
	private const SECTION = 'wso_pp_main';

	/**
	 * Attaches the admin hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'addMenu' ) );
		add_action( 'admin_init', array( $this, 'registerSettings' ) );
		// options.php requires `manage_options` for every option group unless this filter
		// says otherwise, and shop managers do not have it.
		add_filter( 'option_page_capability_' . self::SLUG, array( $this, 'optionPageCapability' ) );
	}

	/**
	 * Allows shop managers to save the page.
	 *
	 * @return string Capability name.
	 */
	public function optionPageCapability(): string {
		return self::CAPABILITY;
	}

	/**
	 * Adds the submenu entry under WooCommerce.
	 */
	public function addMenu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Popular Products', 'wso-popular-products' ),
			__( 'Popular Products', 'wso-popular-products' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Registers the option, its sanitizer and the fields.
	 */
	public function registerSettings(): void {
		register_setting(
			self::SLUG,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		add_settings_section(
			self::SECTION,
			__( 'Currency switcher', 'wso-popular-products' ),
			array( $this, 'renderSection' ),
			self::SLUG
		);

		add_settings_field(
			'wso_pp_currency_enabled',
			__( 'Offer prices in another currency', 'wso-popular-products' ),
			array( $this, 'renderCurrencyField' ),
			self::SLUG,
			self::SECTION
		);
	}

	/**
	 * Prints the section description.
	 */
	public function renderSection(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Shoppers pick the currency themselves on the storefront. This switch removes the selector from every block at once, without editing a single post.', 'wso-popular-products' )
		);
	}

	/**
	 * Prints the checkbox.
	 */
	public function renderCurrencyField(): void {
		$currencies = implode( ', ', Settings::currencies() );

		printf(
			'<label><input type="checkbox" name="%1$s[currency_enabled]" value="1"%2$s> %3$s</label>',
			esc_attr( Settings::OPTION ),
			checked( Settings::currencyEnabled(), true, false ),
			esc_html__( 'Show the currency selector in blocks that enable it', 'wso-popular-products' )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: comma-separated currency codes, e.g. USD, EUR, PLN. */
					__( 'Besides the store currency, shoppers can choose %s. Rates come from the National Bank of Ukraine.', 'wso-popular-products' ),
					$currencies
				)
			)
		);
	}

	/**
	 * Sanitizes the submitted option array.
	 *
	 * @param mixed $input Raw input from options.php.
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();

		return array(
			'currency_enabled' => ! empty( $input['currency_enabled'] ),
		);
	}

	/**
	 * Renders the page.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Popular Products', 'wso-popular-products' ) . '</h1>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		echo '<form method="post" action="options.php">';

		settings_fields( self::SLUG );
		do_settings_sections( self::SLUG );
		submit_button();

		echo '</form></div>';
	}
}
