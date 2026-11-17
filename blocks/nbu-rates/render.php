<?php
/**
 * Block render callback.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wso_rates_heading   = isset( $attributes['heading'] ) && is_scalar( $attributes['heading'] )
	? sanitize_text_field( (string) $attributes['heading'] )
	: '';
$wso_rates_codes_raw = isset( $attributes['codes'] ) && is_scalar( $attributes['codes'] )
	? sanitize_text_field( (string) $attributes['codes'] )
	: '';
$wso_rates_show_date = ! isset( $attributes['showDate'] ) || (bool) $attributes['showDate'];

$wso_rates_codes = \WSO\PopularProducts\Rates\RateProvider::parseCodes( $wso_rates_codes_raw );

$wso_rates_context = array(
	'endpoint'   => esc_url_raw( rest_url( 'wso-popular-products/v1/rates' ) ),
	'codes'      => $wso_rates_codes,
	'showDate'   => $wso_rates_show_date,
	'rates'      => array(),
	'date'       => '',
	'loading'    => false,
	'loaded'     => false,
	'error'      => '',
	'statusText' => '',
	'isEmpty'    => false,
	'hasError'   => false,
	'hasDate'    => false,
	'dateText'   => '',
	// Labels travel from PHP to keep the strings in the text domain.
	'labels'     => array(
		'refresh'    => esc_html__( 'Refresh', 'wso-popular-products' ),
		'loading'    => esc_html__( 'Loading…', 'wso-popular-products' ),
		'failed'     => esc_html__( 'Rates unavailable', 'wso-popular-products' ),
		'empty'      => esc_html__( 'No rates to show.', 'wso-popular-products' ),
		'perUnit'    => esc_html__( 'per 1', 'wso-popular-products' ),
		/* translators: %d: number of currencies shown. */
		'countLabel' => esc_html__( '%d currencies', 'wso-popular-products' ),
		'asOf'       => esc_html__( 'as of', 'wso-popular-products' ),
		'staleHint'  => esc_html__( 'last known rates', 'wso-popular-products' ),
	),
);

$wso_rates_wrapper = (string) get_block_wrapper_attributes( array( 'class' => 'wso-rates' ) );
$wso_rates_attrs   = (string) wp_interactivity_data_wp_context( $wso_rates_context );

$wso_rates_heading_markup = '' !== $wso_rates_heading
	? '<h3 class="wso-rates__heading">' . esc_html( $wso_rates_heading ) . '</h3>'
	: '';

$wso_rates_refresh_label = esc_html__( 'Refresh', 'wso-popular-products' );
$wso_rates_empty_label   = esc_html__( 'No rates to show.', 'wso-popular-products' );
?>
<div
	<?php echo $wso_rates_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper escapes. ?>
	data-wp-interactive="wso/nbu-rates"
	<?php echo $wso_rates_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper escapes. ?>
	data-wp-init="actions.load"
>
	<?php echo $wso_rates_heading_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>

	<div class="wso-rates__toolbar">
		<button
			type="button"
			class="wso-rates__refresh"
			data-wp-on--click="actions.reload"
			data-wp-bind--disabled="context.loading"
		>
			<?php echo $wso_rates_refresh_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
		</button>
		<span class="wso-rates__status" data-wp-text="context.statusText"></span>
	</div>

	<ul class="wso-rates__list" data-wp-class--is-loading="context.loading">
		<template data-wp-each="context.rates">
			<li class="wso-rates__item">
				<span class="wso-rates__code" data-wp-text="context.item.code"></span>
				<span class="wso-rates__value" data-wp-text="context.item.formatted"></span>
				<span class="wso-rates__name" data-wp-text="context.item.name"></span>
			</li>
		</template>
	</ul>

	<p class="wso-rates__empty" data-wp-bind--hidden="!context.isEmpty">
		<?php echo $wso_rates_empty_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
	</p>

	<p class="wso-rates__error" role="alert" data-wp-bind--hidden="!context.hasError" data-wp-text="context.error"></p>

	<p class="wso-rates__date" data-wp-bind--hidden="!context.hasDate" data-wp-text="context.dateText"></p>
</div>
