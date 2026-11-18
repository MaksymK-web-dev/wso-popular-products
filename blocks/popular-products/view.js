/**
 * Front-end behaviour.
 *
 * State lives in the block context, not in the store: a store is page-global, so two
 * instances would share one product list — and one selected currency.
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
 * Builds the rates request: every currency this block offers, in one call.
 *
 * @param {Object} context Block context.
 * @return {URL} Request URL.
 */
const buildRatesUrl = ( context ) => {
	const url = new URL( context.ratesEndpoint, window.location.href );

	if ( context.convertCodes ) {
		url.searchParams.set( 'codes', context.convertCodes );
	}

	return url;
};

/**
 * Formats an amount in the given currency.
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
 * Chooses the price string for one product in the selected currency.
 *
 * Falls back to the store price whenever the rate for the chosen currency is missing:
 * a wrong number is worse than an unconverted one.
 *
 * @param {Object} product Product row.
 * @param {Object} context Block context.
 * @return {string} Price text.
 */
const priceTextFor = ( product, context ) => {
	const code = context.selected;

	if ( ! context.allowConvert || ! code || code === context.baseCurrency ) {
		return product.price;
	}

	const rate = Number( context.rates ? context.rates[ code ] : 0 );
	const value = Number( product.priceValue );

	if ( ! Number.isFinite( rate ) || rate <= 0 || ! Number.isFinite( value ) || value <= 0 ) {
		return product.price;
	}

	return formatConverted( value / rate, code );
};

/**
 * Rewrites `priceText` on every product for the selected currency.
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
		context.convertHint = context.rateAvailable
			? context.labels.converted
			: context.labels.rateUnavailable;
	}
};

/**
 * Fetches every offered rate at once and stores them by code.
 *
 * A missing rate only disables the selector.
 *
 * @param {Object} context Block context.
 * @return {Promise<void>} Resolves when the rate state is settled.
 */
const loadRates = async ( context ) => {
	if ( ! context.allowConvert || ! context.convertCodes ) {
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
		const rates = {};

		for ( const [ code, entry ] of Object.entries( payload?.rates ?? {} ) ) {
			const rate = Number( entry?.rate );

			if ( Number.isFinite( rate ) && rate > 0 ) {
				rates[ code ] = rate;
			}
		}

		context.rates = rates;
		context.rateAvailable = Object.keys( rates ).length > 0;
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
		 * Runs on mount: fetch the rates (when enabled) and the product list.
		 *
		 * Rates are awaited first so the first paint already allows a conversion.
		 *
		 * @return {Generator} Action generator.
		 */
		*load() {
			const context = getContext();

			yield loadRates( context );
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
			yield loadRates( context );
			yield loadProducts( context );
		},

		/**
		 * Applies the currency the shopper picked in the selector.
		 *
		 * @param {Event} event Change event from the `<select>`.
		 */
		selectCurrency( event ) {
			const context = getContext();
			const code = event?.target?.value ?? '';

			if ( ! context.allowConvert || '' === code ) {
				return;
			}

			context.selected = code;
			applyPrices( context );
			syncDerived( context );
		},
	},
} );

export { actions };
