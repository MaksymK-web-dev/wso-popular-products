<?php
/**
 * Plugin Name:       Popular Products Block
 * Plugin URI:        https://github.com/MaksymK-web-dev/wso-popular-products
 * Description:       A Gutenberg block that lists best-selling products, loaded from a REST endpoint without a page reload.
 * Version:           1.0.4
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Maksym Krokhal
 * License:           MIT
 * Text Domain:       wso-popular-products
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:      11.3
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

namespace WSO\PopularProducts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WSO_PP_FILE', __FILE__ );
define( 'WSO_PP_DIR', __DIR__ );

require_once __DIR__ . '/autoload.php';

/**
 * Declares WooCommerce HPOS compatibility; the block reads products, not orders, but the
 * declaration keeps WooCommerce from warning about an untested integration.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Boots the plugin on `init`, once blocks are being registered.
 */
add_action(
	'init',
	static function (): void {
		Plugin::instance()->boot();
	},
	20
);
