/**
 * Front-end behaviour.
 *
 * State lives in the block context, not in the store: a store is page-global, so two
 * instances would share one product list.
 */

import { store, getContext } from '@wordpress/interactivity';

/**
 * Builds the products request URL from the endpoint the server passed in the context.
 *
 * Handles both permalink styles: a pretty path or `?rest_route=`.
 *
 * @param {Object} context Block context.
 * @return {URL} Request URL.
 */
const buildProductsUrl = ( context ) => {
	const url = new URL( context.endpoint, window.location.href );
	url.searchParams.set( 'limit', String( context.limit ) );

	return url;
};

/**
 * Builds the rates request URL for the single currency this block converts to.
 *
 * @param {Object} context Block context.
 * @return {URL} Request URL.
 */
const buildRatesUrl = ( context ) => {
	const url = new URL( context.ratesEndpoint, window.location.href );
	url.searchParams.set( 'codes', context.convertTo );

	return url;
};

/**
 * Formats a converted amount in the target currency.
 *
 * @param {number} amount Amount in the target currency.
 * @param {string} code   ISO currency code.
 * @return {string} Formatted amount.
 */
const formatConverted = ( amount, code ) => {
	try {
		return new Intl.NumberFormat( undefined, {
			style: 'currency',
			currency: code,
			maximumFractionDigits: 2,
		} ).format( amount );
	} catch ( error ) {
		return `${ amount.toFixed( 2 ) } ${ code }`;
	}
};

/**
 * Chooses the price string for one product, given the current toggle state.
 *
 * @param {Object} product Product row.
 * @param {Object} context Block context.
 * @return {string} Price text.
 */
const priceTextFor = ( product, context ) => {
	if ( ! context.showConverted || ! context.rateAvailable ) {
		return product.price;
	}

	const value = Number( product.priceValue );

	if ( ! Number.isFinite( value ) || value <= 0 ) {
		return product.price;
	}

	return formatConverted( value / context.rate, context.convertTo );
};

/**
 * Rewrites `priceText` on every product for the current toggle state.
 *
 * Products are replaced, not mutated, so the API sees a new reference.
 *
 * @param {Object} context Block context.
 */
const applyPrices = ( context ) => {
	context.products = context.products.map( ( product ) => ( {
		...product,
		priceText: priceTextFor( product, context ),
	} ) );
};

/**
 * Recomputes the display strings and flags the markup binds to.
 *
 * @param {Object} context Block context.
 */
const syncDerived = ( context ) => {
	if ( context.loading ) {
		context.statusText = 'Loading…';
	} else if ( context.error ) {
		context.statusText = 'Could not load products.';
	} else if ( context.loaded ) {
		const count = context.products.length;
		context.statusText = count === 1 ? '1 product' : `${ count } products`;
	} else {
		context.statusText = '';
	}

	context.isEmpty =
		context.loaded &&
		! context.loading &&
		'' === context.error &&
		0 === context.products.length;

	context.hasError = '' !== context.error;

	if ( context.allowConvert ) {
		context.toggleLabel = context.showConverted
			? context.labels.toBase
			: context.labels.toConverted;

		context.convertHint = context.rateAvailable
			? context.labels.converted
			: context.labels.rateUnavailable;
	}
};

/**
 * Fetches the conversion rate for this block, if the toggle is enabled.
 *
 * A missing rate only disables the button.
 *
 * @param {Object} context Block context.
 * @return {Promise<void>} Resolves when the rate state is settled.
 */
const loadRate = async ( context ) => {
	if ( ! context.allowConvert || '' === context.convertTo ) {
		return;
	}

	try {
		const response = await fetch( buildRatesUrl( context ).toString(), {
			headers: { Accept: 'application/json' },
		} );

		if ( ! response.ok ) {
			return;
		}

		const payload = await response.json();
		const entry = payload && payload.rates ? payload.rates[ context.convertTo ] : null;
		const rate = entry ? Number( entry.rate ) : 0;

		if ( Number.isFinite( rate ) && rate > 0 ) {
			context.rate = rate;
			context.rateAvailable = true;
		}
	} catch ( error ) {
		// Intentionally silent: no rate means no conversion, which is a valid state.
	}
};

/**
 * Fetches products and writes them into the given context.
 *
 * @param {Object} context Block context.
 * @return {Promise<void>} Resolves when the context has been updated.
 */
const loadProducts = async ( context ) => {
	context.loading = true;
	context.error = '';
	syncDerived( context );

	try {
		const response = await fetch( buildProductsUrl( context ).toString(), {
			headers: { Accept: 'application/json' },
		} );

		if ( ! response.ok ) {
			throw new Error( `HTTP ${ response.status }` );
		}

		const products = await response.json();

		context.products = Array.isArray( products ) ? products : [];
		context.loaded = true;
		applyPrices( context );
	} catch ( error ) {
		context.error =
			error && error.message
				? error.message
				: 'Could not load products.';
		context.products = [];
	} finally {
		context.loading = false;
		syncDerived( context );
	}
};

const { actions } = store( 'wso/popular-products', {
	actions: {
		/**
		 * Runs on mount: fetch the rate (when enabled) and the product list.
		 *
		 * The rate is awaited first so the first paint already has the right prices.
		 *
		 * @return {Generator} Action generator.
		 */
		*load() {
			const context = getContext();

			yield loadRate( context );
			yield loadProducts( context );
		},

		/**
		 * Clears this instance's list and fetches it again.
		 *
		 * @return {Generator} Action generator.
		 */
		*reload() {
			const context = getContext();
			context.products = [];
			context.loaded = false;
			yield loadRate( context );
			yield loadProducts( context );
		},

		/**
		 * Flips between the store currency and the converted one.
		 */
		toggleCurrency() {
			const context = getContext();

			if ( ! context.allowConvert || ! context.rateAvailable ) {
				return;
			}

			context.showConverted = ! context.showConverted;
			applyPrices( context );
			syncDerived( context );
		},
	},
} );

export { actions };
