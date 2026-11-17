<?php
/**
 * Block render callback.
 *
 * Executed by WordPress when the block is rendered on the front end. The markup is a
 * shell: the product list itself is fetched from the REST endpoint by view.js, so the
 * page does not reload and the block can refresh in place.
 *
 * Structure: every decision is taken in the block below, and the template after it only
 * prints prepared strings. No conditionals, no loops and no function calls that decide
 * anything inside the markup.
 *
 * State lives in the block's own `data-wp-context`, not in the store. A store is global to
 * the page, so two instances of this block sharing it meant pressing Refresh in one
 * refreshed both. Context is per element instance, which is what "each block keeps its own
 * list" actually requires.
 *
 * Note on `showImage` and `showPrice`: they are resolved here, in PHP, by including or
 * omitting the element. Inside `data-wp-each` the item context shadows the block context,
 * so a directive that mixes the two (`!context.item.hasImage || !context.showImage`)
 * evaluates the second half as `!undefined` and hides everything. Per-item directives
 * therefore reference `context.item` only.
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

// ─── Input: validated and normalized once ──────────────────────────────

$wso_pp_heading = isset( $attributes['heading'] ) && is_scalar( $attributes['heading'] )
	? sanitize_text_field( (string) $attributes['heading'] )
	: '';

$wso_pp_limit = isset( $attributes['limit'] ) && is_numeric( $attributes['limit'] )
	? min( 12, max( 1, (int) $attributes['limit'] ) )
	: 3;

$wso_pp_show_price = ! isset( $attributes['showPrice'] ) || (bool) $attributes['showPrice'];
$wso_pp_show_image = ! isset( $attributes['showImage'] ) || (bool) $attributes['showImage'];

// ─── Derived values ────────────────────────────────────────────────────

$wso_pp_context = array(
	'endpoint'   => esc_url_raw( rest_url( 'wso-popular-products/v1/products' ) ),
	'limit'      => $wso_pp_limit,
	'showPrice'  => $wso_pp_show_price,
	'showImage'  => $wso_pp_show_image,
	'products'   => array(),
	'loading'    => false,
	'loaded'     => false,
	'error'      => '',
	'statusText' => '',
	'isEmpty'    => false,
	'hasError'   => false,
);

$wso_pp_wrapper_attributes = (string) get_block_wrapper_attributes( array( 'class' => 'wso-pp' ) );
$wso_pp_context_attribute  = (string) wp_interactivity_data_wp_context( $wso_pp_context );

// Optional fragments, so the markup below contains no branches.
$wso_pp_heading_markup = '' !== $wso_pp_heading
	? '<h3 class="wso-pp__heading">' . esc_html( $wso_pp_heading ) . '</h3>'
	: '';

$wso_pp_image_markup = $wso_pp_show_image
	? '<img class="wso-pp__image" alt="" loading="lazy" decoding="async"'
		. ' data-wp-bind--src="context.item.image"'
		. ' data-wp-bind--hidden="!context.item.hasImage">'
	: '';

$wso_pp_price_markup = $wso_pp_show_price
	? '<span class="wso-pp__price" data-wp-text="context.item.price"></span>'
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
