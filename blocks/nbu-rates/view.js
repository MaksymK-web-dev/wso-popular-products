/**
 * Front-end behaviour for the rates block.
 */

import { store, getContext } from '@wordpress/interactivity';

/**
 * Formats a rate with the locale's decimal separator and two fraction digits.
 *
 * The NBU publishes four decimals; two is what a price tag shows.
 *
 * @param {number} rate Rate per one unit of the currency.
 * @return {string} Formatted rate.
 */
const formatRate = ( rate ) => {
	try {
		return new Intl.NumberFormat( undefined, {
			minimumFractionDigits: 2,
			maximumFractionDigits: 4,
		} ).format( rate );
	} catch ( error ) {
		return String( rate );
	}
};

/**
 * Recomputes the display strings the markup binds to.
 *
 * @param {Object} context Block context.
 */
const syncDerived = ( context ) => {
	const count = context.rates.length;

	if ( context.loading ) {
		context.statusText = context.labels.loading;
	} else if ( context.error ) {
		context.statusText = context.labels.failed;
	} else if ( context.loaded ) {
		context.statusText = String( context.labels.countLabel ).replace( '%d', String( count ) );
	} else {
		context.statusText = '';
	}

	context.isEmpty =
		context.loaded &&
		! context.loading &&
		'' === context.error &&
		0 === count;

	context.hasError = '' !== context.error;

	context.hasDate = context.showDate && context.loaded && '' !== context.date;
	context.dateText = context.hasDate ? `${ context.labels.asOf } ${ context.date }` : '';
};

/**
 * Builds the request URL: the block's endpoint plus the codes it displays.
 *
 * @param {Object} context Block context.
 * @return {URL} Request URL.
 */
const buildUrl = ( context ) => {
	const url = new URL( context.endpoint, window.location.href );

	if ( Array.isArray( context.codes ) && context.codes.length > 0 ) {
		url.searchParams.set( 'codes', context.codes.join( ',' ) );
	}

	return url;
};

/**
 * Turns the REST payload into the rows the template iterates.
 *
 * @param {Object} payload REST answer.
 * @param {Object} context Block context.
 * @return {Array<Object>} Rows.
 */
const toRows = ( payload, context ) => {
	const rates = payload && payload.rates ? payload.rates : {};
	const rows = [];

	// Requested order, not payload order: the editor decides what comes first.
	const order = Array.isArray( context.codes ) && context.codes.length > 0
		? context.codes
		: Object.keys( rates );

	for ( const code of order ) {
		if ( ! rates[ code ] ) {
			continue;
		}

		rows.push( {
			code: rates[ code ].code,
			name: rates[ code ].name,
			rate: rates[ code ].rate,
			formatted: formatRate( rates[ code ].rate ),
		} );
	}

	return rows;
};

/**
 * Fetches the rates into the given context.
 *
 * @param {Object} context Block context.
 * @return {Promise<void>} Resolves when the context has been updated.
 */
const loadRates = async ( context ) => {
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

		const payload = await response.json();

		if ( payload && payload.ok === false ) {
			throw new Error( payload.error || context.labels.failed );
		}

		context.rates = toRows( payload, context );
		context.date = payload && payload.date ? payload.date : '';

		if ( payload && payload.stale ) {
			context.date = context.date
				? `${ context.date } (${ context.labels.staleHint })`
				: context.labels.staleHint;
		}

		context.loaded = true;
	} catch ( error ) {
		context.error =
			error && error.message
				? error.message
				: context.labels.failed;
		context.rates = [];
	} finally {
		context.loading = false;
		syncDerived( context );
	}
};

const { actions } = store( 'wso/nbu-rates', {
	actions: {
		/**
		 * Runs on mount.
		 *
		 * @return {Generator} Action generator.
		 */
		*load() {
			yield loadRates( getContext() );
		},

		/**
		 * Clears this instance's rows and fetches them again.
		 *
		 * @return {Generator} Action generator.
		 */
		*reload() {
			const context = getContext();
			context.rates = [];
			context.loaded = false;
			yield loadRates( context );
		},
	},
} );

export { actions };
