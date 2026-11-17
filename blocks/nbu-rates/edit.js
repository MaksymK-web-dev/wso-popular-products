/**
 * Editor behaviour for the rates block.
 *
 * No JSX and no build step, same as the products block: the file ships as readable source
 * and works from a git clone.
 */

const { registerBlockType } = wp.blocks;
const { createElement: el, Fragment, useEffect, useState } = wp.element;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { PanelBody, TextControl, ToggleControl, Spinner, Notice } = wp.components;
const { __ } = wp.i18n;
// wp.apiFetch is the function itself, not an object with a method on it.
const apiFetch = wp.apiFetch;

const NAMESPACE = 'wso/nbu-rates';

/**
 * Normalizes a comma-separated list the way the server does.
 *
 * Mirrors RateProvider::parseCodes(): three upper-case letters, duplicates removed.
 *
 * @param {string} list Raw list.
 * @return {Array<string>} Codes.
 */
const parseCodes = ( list ) =>
	Array.from(
		new Set(
			String( list || '' )
				.split( ',' )
				.map( ( code ) => code.trim().toUpperCase() )
				.filter( ( code ) => /^[A-Z]{3}$/.test( code ) )
		)
	);

/**
 * Preview rendered from the same REST route the front end uses.
 *
 * @param {Object} props Component props.
 * @return {Object} Element tree.
 */
const Preview = ( { heading, codes, showDate } ) => {
	const [ payload, setPayload ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		let cancelled = false;
		setPayload( null );
		setError( '' );

		const parsed = parseCodes( codes );
		const query = parsed.length > 0 ? `?codes=${ parsed.join( ',' ) }` : '';

		apiFetch( { path: `/wso-popular-products/v1/rates${ query }` } )
			.then( ( result ) => {
				if ( cancelled ) {
					return;
				}

				if ( result && result.ok === false ) {
					setError( result.error || __( 'Rates unavailable.', 'wso-popular-products' ) );

					return;
				}

				setPayload( result );
			} )
			.catch( ( reason ) => {
				if ( ! cancelled ) {
					setError( reason?.message || __( 'Rates unavailable.', 'wso-popular-products' ) );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ codes ] );

	const rows = [];

	if ( payload && payload.rates ) {
		const order = parseCodes( codes );

		for ( const code of order.length > 0 ? order : Object.keys( payload.rates ) ) {
			if ( payload.rates[ code ] ) {
				rows.push( payload.rates[ code ] );
			}
		}
	}

	return el(
		'div',
		{ className: 'wso-rates wso-rates--editor' },
		heading ? el( 'h3', { className: 'wso-rates__heading' }, heading ) : null,
		el(
			'div',
			{ className: 'wso-rates__toolbar' },
			el( 'span', { className: 'wso-rates__badge' }, __( 'Preview', 'wso-popular-products' ) ),
			rows.length > 0
				? el( 'span', { className: 'wso-rates__status' }, `${ rows.length }` )
				: null
		),
		error ? el( Notice, { status: 'error', isDismissible: false }, error ) : null,
		payload === null && ! error
			? el(
					'div',
					{ className: 'wso-rates__loading' },
					el( Spinner, {} ),
					el( 'span', {}, __( 'Loading rates…', 'wso-popular-products' ) )
			  )
			: null,
		payload && rows.length === 0
			? el( 'p', { className: 'wso-rates__empty' }, __( 'No rates for these codes.', 'wso-popular-products' ) )
			: null,
		rows.length > 0
			? el(
					'ul',
					{ className: 'wso-rates__list' },
					rows.map( ( row ) =>
						el(
							'li',
							{ key: row.code, className: 'wso-rates__item' },
							el( 'span', { className: 'wso-rates__code' }, row.code ),
							el( 'span', { className: 'wso-rates__value' }, String( row.rate ) ),
							el( 'span', { className: 'wso-rates__name' }, row.name )
						)
					)
			  )
			: null,
		showDate && payload && payload.date
			? el( 'p', { className: 'wso-rates__date' }, payload.date )
			: null
	);
};

registerBlockType( NAMESPACE, {
	attributes: {
		heading: { type: 'string', default: 'Exchange rates' },
		codes: { type: 'string', default: 'USD,EUR,PLN' },
		showDate: { type: 'boolean', default: true },
	},

	edit: ( { attributes, setAttributes } ) => {
		const { heading, codes, showDate } = attributes;
		const blockProps = useBlockProps( { className: 'wso-rates' } );

		return el(
			Fragment,
			{},
			el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Content', 'wso-popular-products' ), initialOpen: true },
					el( TextControl, {
						label: __( 'Heading', 'wso-popular-products' ),
						value: heading,
						onChange: ( value ) => setAttributes( { heading: value } ),
						help: __( 'Leave empty to hide the heading.', 'wso-popular-products' ),
					} ),
					el( TextControl, {
						label: __( 'Currency codes', 'wso-popular-products' ),
						value: codes,
						onChange: ( value ) => setAttributes( { codes: value } ),
						help: __( 'Comma-separated ISO codes, e.g. USD,EUR,PLN.', 'wso-popular-products' ),
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Display', 'wso-popular-products' ), initialOpen: true },
					el( ToggleControl, {
						label: __( 'Show the date the rates were published', 'wso-popular-products' ),
						checked: showDate,
						onChange: ( value ) => setAttributes( { showDate: value } ),
					} )
				)
			),
			el( 'div', blockProps, el( Preview, { heading, codes, showDate } ) )
		);
	},

	// Dynamic block: the front end is rendered by render.php.
	save: () => null,
} );
