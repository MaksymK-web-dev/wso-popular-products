<?php
/**
 * Block render callback.
 *
 * The markup is a shell; view.js fills the list from the REST endpoint. Every decision is
 * taken before the first `?>`, so the template only prints prepared strings.
 *
 * @package WSO\PopularProducts
 *
 * @var array<string, mixed> $attributes Block attributes, already unslashed by core.
 * @var string               $content    Inner block content (unused).
 * @var WP_Block             $block      Block instance.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Input: validated and normalized once.

$wso_pp_heading = isset( $attributes['heading'] ) && is_scalar( $attributes['heading'] )
	? sanitize_text_field( (string) $attributes['heading'] )
	: '';

$wso_pp_limit = isset( $attributes['limit'] ) && is_numeric( $attributes['limit'] )
	? min( 12, max( 1, (int) $attributes['limit'] ) )
	: 3;

$wso_pp_show_price = ! isset( $attributes['showPrice'] ) || (bool) $attributes['showPrice'];
$wso_pp_show_image = ! isset( $attributes['showImage'] ) || (bool) $attributes['showImage'];

// Three gates have to agree: a visible price, this block's own opt-in, and the
// site-wide switch under WooCommerce → Popular Products.
$wso_pp_allow_convert = $wso_pp_show_price
	&& ( isset( $attributes['showCurrencyToggle'] ) && (bool) $attributes['showCurrencyToggle'] )
	&& \WSO\PopularProducts\Config\Settings::currencyEnabled();

// Derived values.

$wso_pp_base = function_exists( 'get_woocommerce_currency' )
	? strtoupper( (string) get_woocommerce_currency() )
	: 'UAH';

$wso_pp_codes = $wso_pp_allow_convert ? \WSO\PopularProducts\Config\Settings::currencies() : array();

$wso_pp_options = array(
	array(
		'code'  => $wso_pp_base,
		'label' => $wso_pp_base,
	),
);

foreach ( $wso_pp_codes as $wso_pp_code ) {
	if ( $wso_pp_code === $wso_pp_base ) {
		continue;
	}

	$wso_pp_options[] = array(
		'code'  => $wso_pp_code,
		'label' => $wso_pp_code,
	);
}

$wso_pp_context = array(
	'endpoint'      => esc_url_raw( rest_url( 'wso-popular-products/v1/products' ) ),
	'ratesEndpoint' => esc_url_raw( rest_url( 'wso-popular-products/v1/rates' ) ),
	'limit'         => $wso_pp_limit,
	'showPrice'     => $wso_pp_show_price,
	'showImage'     => $wso_pp_show_image,
	'allowConvert'  => $wso_pp_allow_convert,
	// The shopper's choice starts at the store currency, so a first paint never
	// shows a price in a currency the shop did not choose.
	'baseCurrency'  => $wso_pp_base,
	'selected'      => $wso_pp_base,
	'convertCodes'  => implode( ',', array_map( 'strval', $wso_pp_codes ) ),
	'rates'         => array(),
	'rateAvailable' => false,
	'convertHint'   => '',
	'products'      => array(),
	'loading'       => false,
	'loaded'        => false,
	'error'         => '',
	'statusText'    => '',
	'isEmpty'       => false,
	'hasError'      => false,
	// Labels travel from PHP to keep the strings in the text domain.
	'labels'        => array(
		'currency'        => esc_html__( 'Currency', 'wso-popular-products' ),
		'converted'       => esc_html__( 'Converted at the NBU rate', 'wso-popular-products' ),
		'rateUnavailable' => esc_html__( 'Exchange rate unavailable', 'wso-popular-products' ),
	),
);

$wso_pp_wrapper_attributes = (string) get_block_wrapper_attributes( array( 'class' => 'wso-pp' ) );
$wso_pp_context_attribute  = (string) wp_interactivity_data_wp_context( $wso_pp_context );

// Optional fragments, so the markup below contains no branches. Inside data-wp-each the
// item context shadows the block context: per-item directives read `context.item` only.
$wso_pp_heading_markup = '' !== $wso_pp_heading
	? '<h3 class="wso-pp__heading">' . esc_html( $wso_pp_heading ) . '</h3>'
	: '';

$wso_pp_image_markup = $wso_pp_show_image
	? '<img class="wso-pp__image" alt="" loading="lazy" decoding="async"'
		. ' data-wp-bind--src="context.item.image"'
		. ' data-wp-bind--hidden="!context.item.hasImage">'
	: '';

$wso_pp_price_markup = $wso_pp_show_price
	? '<span class="wso-pp__price" data-wp-text="context.item.priceText"></span>'
	: '';

// The shopper chooses; the server only decides whether the control exists at all and
// which options it offers.
$wso_pp_currency_options = '';

foreach ( $wso_pp_options as $wso_pp_option ) {
	$wso_pp_currency_options .= sprintf(
		'<option value="%s">%s</option>',
		esc_attr( (string) $wso_pp_option['code'] ),
		esc_html( (string) $wso_pp_option['label'] )
	);
}

$wso_pp_currency_markup = $wso_pp_allow_convert && count( $wso_pp_options ) > 1
	? '<label class="wso-pp__currency">'
		. '<span class="screen-reader-text">' . esc_html__( 'Currency', 'wso-popular-products' ) . '</span>'
		. '<select data-wp-on--change="actions.selectCurrency"'
		. ' data-wp-bind--disabled="!context.rateAvailable"'
		. ' data-wp-bind--title="context.convertHint">'
		. $wso_pp_currency_options
		. '</select></label>'
	: '';

$wso_pp_refresh_label = esc_html__( 'Refresh', 'wso-popular-products' );
$wso_pp_empty_label   = esc_html__( 'No products found.', 'wso-popular-products' );
?>
<div
	<?php echo $wso_pp_wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper escapes. ?>
	data-wp-interactive="wso/popular-products"
	<?php echo $wso_pp_context_attribute; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper escapes. ?>
	data-wp-init="actions.load"
>
	<?php echo $wso_pp_heading_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>

	<div class="wso-pp__toolbar">
		<button
			type="button"
			class="wso-pp__refresh"
			data-wp-on--click="actions.reload"
			data-wp-bind--disabled="context.loading"
		>
			<?php echo $wso_pp_refresh_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
		</button>
		<span class="wso-pp__status" data-wp-text="context.statusText"></span>
		<?php echo $wso_pp_currency_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built above. ?>
	</div>

	<ul class="wso-pp__list" data-wp-class--is-loading="context.loading">
		<template data-wp-each="context.products">
			<li class="wso-pp__item">
				<a class="wso-pp__link" data-wp-bind--href="context.item.permalink">
					<?php echo $wso_pp_image_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built above. ?>
					<span class="wso-pp__name" data-wp-text="context.item.name"></span>
				</a>
				<?php echo $wso_pp_price_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built above. ?>
			</li>
		</template>
	</ul>

	<p class="wso-pp__empty" data-wp-bind--hidden="!context.isEmpty">
		<?php echo $wso_pp_empty_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
	</p>

	<p class="wso-pp__error" role="alert" data-wp-bind--hidden="!context.hasError" data-wp-text="context.error"></p>
</div>
