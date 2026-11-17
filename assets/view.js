/**
 * Front-end behaviour.
 *
 * Loaded by block.json as a script module, so it can import the Interactivity API.
 * The block's markup is server-rendered once; everything after that — the first load and
 * every refresh — goes through the REST endpoint and patches the existing DOM. No page
 * reload, no full block re-render.
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
 * Fetches products and updates the shared state.
 *
 * @param {Object} context Block context.
 * @return {Promise<void>} Resolves when the state has been updated.
 */
const loadProducts = async ( context ) => {
	const { state } = store( 'wso/popular-products' );

	state.loading = true;
	state.error = '';

	try {
		const response = await fetch( buildUrl( context ).toString(), {
			headers: { Accept: 'application/json' },
		} );

		if ( ! response.ok ) {
			throw new Error( `HTTP ${ response.status }` );
		}

		const products = await response.json();

		state.products = Array.isArray( products ) ? products : [];
		state.loaded = true;
	} catch ( error ) {
		state.error =
			error && error.message
				? error.message
				: 'Could not load products.';
	} finally {
		state.loading = false;
	}
};

const { state, actions } = store( 'wso/popular-products', {
	state: {
		/**
		 * Products as returned by the REST endpoint.
		 *
		 * @type {Array<Object>}
		 */
		products: [],
		loading: false,
		loaded: false,
		error: '',

		/**
		 * Status line shown next to the refresh button.
		 *
		 * @return {string} Human-readable status.
		 */
		get statusText() {
			if ( state.loading ) {
				return 'Loading…';
			}

			if ( state.error ) {
				return 'Could not load products.';
			}

			if ( state.loaded ) {
				const count = state.products.length;
				return count === 1 ? '1 product' : `${ count } products`;
			}

			return '';
		},

		/**
		 * Whether the request finished and returned nothing.
		 *
		 * @return {boolean} True when the empty message should be visible.
		 */
		get isEmpty() {
			return (
				state.loaded &&
				! state.loading &&
				'' === state.error &&
				0 === state.products.length
			);
		},

		/**
		 * Whether the error line should be visible.
		 *
		 * @return {boolean} True when there is an error to show.
		 */
		get hasError() {
			return '' !== state.error;
		},
	},

	actions: {
		/**
		 * Runs on mount: fetch the list.
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
		 * Clears the list and fetches it again.
		 *
		 * @return {Generator} Action generator.
		 */
		*reload() {
			state.products = [];
			state.loaded = false;
			yield loadProducts( getContext() );
		},
	},
} );

// Keeps the module's exports referenced for tooling that tree-shakes aggressively.
export { state, actions };
