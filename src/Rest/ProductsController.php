<?php
/**
 * REST endpoint for the block.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

namespace WSO\PopularProducts\Rest;

use WSO\PopularProducts\Contract\HookRegistrar;

/**
 * Serves the product list the block renders.
 *
 * The block could call WooCommerce's Store API directly, and that would work. This proxy
 * exists for three reasons worth naming:
 *
 *   1. The response shape is ours, so the client never has to know WooCommerce's payload.
 *   2. Results are cached in a transient, so a popular page does not re-query the
 *      database on every visitor.
 *   3. The endpoint stays alive when WooCommerce's Store API is unavailable (for example
 *      on a site where the blocks package is disabled), returning an empty list instead
 *      of a fatal error.
 */
final class ProductsController implements HookRegistrar {

	private const NAMESPACE = 'wso-popular-products/v1';

	private const ROUTE = '/products';

	private const CACHE_KEY = 'wso_pp_products_';

	private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	/**
	 * Registers the route.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getProducts' ),
				// Public data: this is the same catalogue the storefront shows.
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'type'              => 'integer',
						'default'           => 3,
						'minimum'           => 1,
						'maximum'           => 12,
						'sanitize_callback' => 'absint',
						'validate_callback' => static fn ( $value ): bool => is_numeric( $value )
							&& (int) $value >= 1
							&& (int) $value <= 12,
					),
				),
			)
		);
	}

	/**
	 * Returns the best-selling products.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function getProducts( \WP_REST_Request $request ) {
		$limit = max( 1, min( 12, (int) $request->get_param( 'limit' ) ) );

		$cached = get_transient( self::CACHE_KEY . $limit );
		if ( is_array( $cached ) ) {
			return rest_ensure_response( $cached );
		}

		if ( ! function_exists( 'wc_get_products' ) ) {
			return rest_ensure_response( array() );
		}

		$products = wc_get_products(
			array(
				'status'   => 'publish',
				'limit'    => $limit,
				// WooCommerce keeps lifetime sales in this meta key; wc_get_products() has no
				// "popularity" argument (that lives in WC_Query::order_by_popularity_post_clauses).
				// The date tie-break matters: on a store where nothing has sold yet every product
				// has total_sales = 0, and without it the order is whatever the database returns —
				// a newly added product could land anywhere, including outside the limit.
				'orderby'  => array(
					'meta_value_num' => 'DESC',
					'date'           => 'DESC',
				),
				'meta_key' => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		$payload = array_map( array( $this, 'toArray' ), $products );

		set_transient( self::CACHE_KEY . $limit, $payload, self::CACHE_TTL );

		return rest_ensure_response( $payload );
	}

	/**
	 * Maps a product to the response shape.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string, mixed>
	 */
	private function toArray( \WC_Product $product ): array {
		$image_id  = (int) $product->get_image_id();
		$image_url = $image_id > 0 ? (string) wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';

		return array(
			'id'         => $product->get_id(),
			'name'       => $product->get_name(),
			'permalink'  => (string) get_permalink( $product->get_id() ),
			'price'      => $this->formatPrice( $product ),
			// The formatted string is for display; the number is for conversion. Sending both
			// keeps the client from having to parse "129,00 ₴" back into a float.
			'priceValue' => (float) $product->get_price(),
			'currency'   => (string) get_woocommerce_currency(),
			'image'      => $image_url,
			'hasImage'   => '' !== $image_url,
		);
	}

	/**
	 * Formats the price as plain text.
	 *
	 * The price is built from wc_price() rather than get_price_html(): the latter emits
	 * markup plus screen-reader sentences ("Original price was… Current price is…"), and
	 * its HTML entities survive wp_strip_all_tags() — the first version of this endpoint
	 * returned `149,00&nbsp;&#8372;` straight into the block, where data-wp-text set it as
	 * literal text. wc_price() plus a single decode gives "149,00 ₴".
	 *
	 * @param \WC_Product $_product Product.
	 * @return string Plain-text price.
	 */
	private function formatPrice( \WC_Product $_product ): string {
		$price = $_product->get_price();

		if ( '' === (string) $price ) {
			return '';
		}

		return html_entity_decode(
			wp_strip_all_tags( wc_price( (float) $price ) ),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);
	}
}
