<?php
/**
 * REST endpoint for exchange rates.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

namespace WSO\PopularProducts\Rates;

use WSO\PopularProducts\Contract\HookRegistrar;

/**
 * Serves the rate table the blocks read.
 *
 * `?codes=USD,EUR` narrows the answer to what a block actually displays: the full NBU
 * table is ~40 currencies, and shipping all of them to the browser per request is waste.
 * Invalid codes are dropped rather than rejected, so a typo degrades to "fewer rates"
 * instead of an empty page.
 */
final class RatesController implements HookRegistrar {

	/**
	 * REST namespace, shared with the products route.
	 */
	private const NAMESPACE = 'wso-popular-products/v1';

	/**
	 * Route path.
	 */
	private const ROUTE = '/rates';

	/**
	 * Maximum number of currencies a single request may ask for.
	 */
	private const MAX_CODES = 12;

	/**
	 * Sets up the controller.
	 *
	 * @param RateProvider $provider Rate source.
	 */
	public function __construct( private readonly RateProvider $provider ) {}

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
				'callback'            => array( $this, 'getRates' ),
				// Public data: published central-bank rates, identical for every visitor.
				'permission_callback' => '__return_true',
				'args'                => array(
					'codes' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static fn ( $value ): bool => is_string( $value ) && strlen( $value ) <= 120,
					),
				),
			)
		);
	}

	/**
	 * Returns the requested rates.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function getRates( \WP_REST_Request $request ): \WP_REST_Response {
		$table = $this->provider->table();

		$codes = RateProvider::parseCodes( (string) $request->get_param( 'codes' ) );

		if ( array() !== $codes ) {
			$table['rates'] = array_intersect_key( $table['rates'], array_flip( array_slice( $codes, 0, self::MAX_CODES ) ) );
		}

		return rest_ensure_response( $table );
	}
}
