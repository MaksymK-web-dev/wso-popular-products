<?php
/**
 * Block render callback.
 *
 * Executed by WordPress when the block is rendered on the front end. The markup is a
 * shell: the product list itself is fetched from the REST endpoint by view.js, so the
 * page does not reload and the block can refresh in place.
 *
 * Server-rendering the shell (instead of returning nothing) keeps the block visible in
 * the editor preview, gives the Interactivity API a hydration target, and means the markup
 * degrades to a heading and a status line when JavaScript is unavailable.
 *
 * State lives in the block's own `data-wp-context`, not in the store. A store is global to
 * the page, so two instances of this block sharing it meant pressing Refresh in one
 * refreshed both — the initial version did exactly that. Context is per element instance,
 * which is what "each block keeps its own list" actually requires.
 *
 * Note on the markup below: `showImage` and `showPrice` are decided here, in PHP, by
 * rendering the element or not. Inside `data-wp-each` the item context shadows the block
 * context, so a directive that mixes the two (`!context.item.hasImage ||
 * !context.showImage`) evaluates the second half as `!undefined` and hides everything.
 * Per-item directives therefore only reference `context.item`.
 *
 * @package WSO\PopularProducts
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Inner block content (unused).
 * @var WP_Block             $block      Block instance.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$wso_pp_heading    = isset( $attributes['heading'] ) ? (string) $attributes['heading'] : '';
$wso_pp_limit      = isset( $attributes['limit'] ) ? max( 1, min( 12, (int) $attributes['limit'] ) ) : 3;
$wso_pp_show_price = ! isset( $attributes['showPrice'] ) || (bool) $attributes['showPrice'];
$wso_pp_show_image = ! isset( $attributes['showImage'] ) || (bool) $attributes['showImage'];

$wso_pp_context = array(
	'endpoint'   => esc_url_raw( rest_url( 'wso-popular-products/v1/products' ) ),
	'limit'      => $wso_pp_limit,
	'showPrice'  => $wso_pp_show_price,
	'showImage'  => $wso_pp_show_image,
	// Runtime state, written by view.js. Declared here so the first render has a shape.
	'products'   => array(),
	'loading'    => false,
	'loaded'     => false,
	'error'      => '',
	'statusText' => '',
	'isEmpty'    => false,
	'hasError'   => false,
);
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'wso-pp' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper escapes. ?>
	data-wp-interactive="wso/popular-products"
	<?php echo wp_interactivity_data_wp_context( $wso_pp_context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper escapes. ?>
	data-wp-init="actions.load"
>
	<?php if ( '' !== $wso_pp_heading ) : ?>
		<h3 class="wso-pp__heading"><?php echo esc_html( $wso_pp_heading ); ?></h3>
	<?php endif; ?>

	<div class="wso-pp__toolbar">
		<button
			type="button"
			class="wso-pp__refresh"
			data-wp-on--click="actions.reload"
			data-wp-bind--disabled="context.loading"
		>
			<?php esc_html_e( 'Refresh', 'wso-popular-products' ); ?>
		</button>
		<span class="wso-pp__status" data-wp-text="context.statusText"></span>
	</div>

	<ul class="wso-pp__list" data-wp-class--is-loading="context.loading">
		<template data-wp-each="context.products">
			<li class="wso-pp__item">
				<a class="wso-pp__link" data-wp-bind--href="context.item.permalink">
					<?php if ( $wso_pp_show_image ) : ?>
						<img
							class="wso-pp__image"
							alt=""
							loading="lazy"
							decoding="async"
							data-wp-bind--src="context.item.image"
							data-wp-bind--hidden="!context.item.hasImage"
						>
					<?php endif; ?>
					<span class="wso-pp__name" data-wp-text="context.item.name"></span>
				</a>
				<?php if ( $wso_pp_show_price ) : ?>
					<span class="wso-pp__price" data-wp-text="context.item.price"></span>
				<?php endif; ?>
			</li>
		</template>
	</ul>

	<p class="wso-pp__empty" data-wp-bind--hidden="!context.isEmpty">
		<?php esc_html_e( 'No products found.', 'wso-popular-products' ); ?>
	</p>

	<p class="wso-pp__error" role="alert" data-wp-bind--hidden="!context.hasError" data-wp-text="context.error"></p>
</div>
