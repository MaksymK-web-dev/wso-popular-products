/**
 * End-to-end check for the currency toggle, driven through the Chrome DevTools Protocol.
 *
 * Usage:
 *   chrome --headless=new --remote-debugging-port=9222 --user-data-dir=<tmp> about:blank
 *   node tools/verify-toggle.mjs http://localhost:9222 https://example.test/?page_id=41
 */

const [, , devtoolsBase, pageUrl] = process.argv;

if ( ! devtoolsBase || ! pageUrl ) {
	console.error( 'usage: node verify-toggle.mjs <devtools-base> <page-url>' );
	process.exit( 2 );
}

/**
 * Sends one CDP command and resolves with its result.
 *
 * @param {WebSocket} socket Open DevTools socket.
 * @param {number} id      Command id.
 * @param {string} method  CDP method.
 * @param {Object} params  Method params.
 * @param {Map} pending    Pending request map.
 * @return {Promise<Object>} Command result.
 */
const send = ( socket, id, method, params, pending ) =>
	new Promise( ( resolve, reject ) => {
		pending.set( id, { resolve, reject } );
		socket.send( JSON.stringify( { id, method, params } ) );
	} );

/**
 * Evaluates an expression in the page and returns its JSON value.
 *
 * @param {WebSocket} socket Open DevTools socket.
 * @param {string} expression JS expression.
 * @param {number} nextId Mutable id counter holder.
 * @param {Map} pending Pending request map.
 * @return {Promise<*>} Value.
 */
const evaluate = async ( socket, expression, nextId, pending ) => {
	const id = nextId.value++;
	const result = await send( socket, id, 'Runtime.evaluate', {
		expression,
		awaitPromise: true,
		returnByValue: true,
	}, pending );

	if ( result.exceptionDetails ) {
		throw new Error( result.exceptionDetails.text );
	}

	return result.result.value;
};

// --- find the page target ---
const targets = await ( await fetch( `${ devtoolsBase }/json/list` ) ).json();
const target = targets.find( ( t ) => t.type === 'page' );

if ( ! target ) {
	console.error( 'no page target' );
	process.exit( 1 );
}

const socket = new WebSocket( target.webSocketDebuggerUrl );
const pending = new Map();
const nextId = { value: 1 };

socket.addEventListener( 'message', ( event ) => {
	const message = JSON.parse( event.data );
	const entry = pending.get( message.id );

	if ( entry ) {
		pending.delete( message.id );
		message.error ? entry.reject( new Error( message.error.message ) ) : entry.resolve( message.result );
	}
} );

await new Promise( ( resolve ) => socket.addEventListener( 'open', resolve, { once: true } ) );

await send( socket, nextId.value++, 'Page.enable', {}, pending );
await send( socket, nextId.value++, 'Runtime.enable', {}, pending );
await send( socket, nextId.value++, 'Page.navigate', { url: pageUrl }, pending );

// --- wait for the block to hydrate ---
const waitFor = async ( expression, timeoutMs = 15000 ) => {
	const started = Date.now();

	while ( Date.now() - started < timeoutMs ) {
		const value = await evaluate( socket, expression, nextId, pending );
		if ( value ) {
			return value;
		}
		await new Promise( ( resolve ) => setTimeout( resolve, 250 ) );
	}

	throw new Error( `timed out waiting for: ${ expression }` );
};

await waitFor( `!!document.querySelector( '.wso-pp__currency select' )` );

// Wait for a loaded state: the shell exists after the first paint, before the data arrives.
await waitFor(
	`(() => {
		const select = document.querySelector( '.wso-pp__currency select' );
		const price = document.querySelector( '.wso-pp__price' );
		return !!select && !select.disabled &&
			!!price && price.textContent.trim().length > 0;
	})()`
);

const pricesNow = () =>
	evaluate(
		socket,
		`Array.from( document.querySelectorAll( '.wso-pp__price' ) ).map( ( n ) => n.textContent.trim() )`,
		nextId,
		pending
	);

const selectedNow = () =>
	evaluate( socket, `document.querySelector( '.wso-pp__currency select' ).value`, nextId, pending );

/**
 * Picks a currency in the selector and lets the change event settle.
 *
 * @param {string} code ISO code.
 */
const choose = async ( code ) => {
	await evaluate(
		socket,
		`(() => {
			const select = document.querySelector( '.wso-pp__currency select' );
			select.value = ${ JSON.stringify( code ) };
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		})()`,
		nextId,
		pending
	);
	await new Promise( ( resolve ) => setTimeout( resolve, 600 ) );
};

const options = await evaluate(
	socket,
	`Array.from( document.querySelector( '.wso-pp__currency select' ).options ).map( ( o ) => o.value )`,
	nextId,
	pending
);
const base = options[ 0 ];
const targetCode = options.find( ( code ) => 'USD' === code ) || options[ 1 ];

const before = await pricesNow();
const selectedBefore = await selectedNow();

await choose( targetCode );
const after = await pricesNow();
const selectedAfter = await selectedNow();

// --- back to the store currency: the original strings must return ---
await choose( base );
const back = await pricesNow();

// --- rates block ---
const rateRows = await evaluate(
	socket,
	`Array.from( document.querySelectorAll( '.wso-rates__code' ) ).map( ( n ) => n.textContent.trim() )`,
	nextId,
	pending
);

console.log( 'selector      :', options.join( ', ' ) );
console.log( 'prices before :', before.join( ' | ' ), '(selected ' + selectedBefore + ')' );
console.log( 'prices ' + targetCode + '  :', after.join( ' | ' ), '(selected ' + selectedAfter + ')' );
console.log( 'prices back   :', back.join( ' | ' ) );
console.log( 'rate codes    :', rateRows.join( ', ' ) );

const changed = before.every( ( value, index ) => value !== after[ index ] );
const restored = before.every( ( value, index ) => value === back[ index ] );
const selectedMoved = selectedBefore !== selectedAfter && selectedAfter === targetCode;
const convertedLooksUsd = 'USD' !== targetCode || after.every( ( value ) => /US\$|\$|\u0024/.test( value ) );

console.log();
console.log( changed ? 'OK   prices changed with the selector' : 'FAIL prices did not change' );
console.log( restored ? 'OK   prices came back with the store currency' : 'FAIL prices did not come back' );
console.log( selectedMoved ? 'OK   the selector reports the chosen currency' : 'FAIL selector state drifted' );
console.log( convertedLooksUsd ? 'OK   converted prices look like the target currency' : 'FAIL converted prices are wrong' );
console.log( rateRows.length > 0 ? 'OK   rates block rendered rows' : 'FAIL rates block empty' );

socket.close();
process.exit( changed && restored && selectedMoved && convertedLooksUsd && rateRows.length > 0 ? 0 : 1 );
