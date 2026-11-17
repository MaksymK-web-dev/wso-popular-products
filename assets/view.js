/**
 * Front-end behaviour.
 *
 * Loaded by block.json as a script module, so it can import the Interactivity API.
 * The block's markup is server-rendered once; everything after that — the first load and
 * every refresh — goes through the REST endpoint and patches the existing DOM. No page
 * reload, no full block re-render.
 *
 * State lives in the block's context, not in the store. A store is page-global: two
 * instances of this block sharing `state.products` meant pressing Refresh in one updated
 * both. Context is per element instance, which is what the UI implies.
 */

import { store, getContext } from '@wordpress/interactivity';

/**
 * Builds the request URL from the endpoint the server passed in the block context.
 *
 * Handles both permalink styles: `rest_url()` returns a pretty path on some sites and
 * `?rest_route=` on others, and `URL.searchParams` appends correctly to either.
 *
 * @param {Object} context Block context.
 * @return {URL} Request URL.
 */
const buildUrl = ( context ) => {
	const url = new URL( context.endpoint, window.location.href );
	url.searchParams.set( 'limit', String( context.limit ) );

	return url;
};

/**
 * Derives the display strings and flags the markup binds to.
 *
 * Context holds plain data — no getters — so the derived values are written here whenever
 * the underlying state changes.
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
		const response = await fetch( buildUrl( context ).toString(), {
			headers: { Accept: 'application/json' },
		} );

		if ( ! response.ok ) {
			throw new Error( `HTTP ${ response.status }` );
		}

		const products = await response.json();

		context.products = Array.isArray( products ) ? products : [];
		context.loaded = true;
	} catch ( error ) {
		context.error =
			error && error.message
				? error.message
				: 'Could not load products.';
	} finally {
		context.loading = false;
		syncDerived( context );
	}
};

const { actions } = store( 'wso/popular-products', {
	actions: {
		/**
		 * Runs on mount: fetch the list for this block instance.
		 *
		 * A generator so the Interactivity API can await the promise and keep the
		 * directive's error handling intact.
		 *
		 * @return {Generator} Action generator.
		 */
		*load() {
			yield loadProducts( getContext() );
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
			yield loadProducts( context );
		},
	},
} );

// Keeps the module's export referenced for tooling that tree-shakes aggressively.
export { actions };
