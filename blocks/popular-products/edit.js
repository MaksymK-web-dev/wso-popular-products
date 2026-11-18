/**
 * Editor behaviour for the popular-products block.
 *
 * A classic script, so the whole file is one IIFE: the `edit.js` of every block runs
 * in the same global scope, and a top-level declaration there collides with another
 * block's script — the second one to load would fail to parse. Everything this block
 * needs stays inside the wrapper.
 */

( function ( wp ) {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment, useEffect, useState } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, RangeControl, TextControl, ToggleControl, Spinner, Notice } = wp.components;
	const { __ } = wp.i18n;
	// wp.apiFetch is the function itself, not an object with a method on it.
	const apiFetch = wp.apiFetch;

	const BLOCK_NAME = 'wso/popular-products';
	const PRODUCTS_PATH = '/wso-popular-products/v1/products';

	/**
	 * Preview rendered from the plugin's own REST endpoint.
	 *
	 * @param {Object} props Component props.
	 * @return {Object} Element tree.
	 */
	const Preview = ( { limit, showPrice, showImage, heading } ) => {
		const [ products, setProducts ] = useState( null );
		const [ error, setError ] = useState( '' );

		useEffect( () => {
			let cancelled = false;
			setProducts( null );
			setError( '' );

			apiFetch( {
				path: `${ PRODUCTS_PATH }?limit=${ limit }`,
			} )
				.then( ( result ) => {
					if ( ! cancelled ) {
						setProducts( Array.isArray( result ) ? result : [] );
					}
				} )
				.catch( ( reason ) => {
					if ( ! cancelled ) {
						setError( reason?.message || __( 'Could not load products.', 'wso-popular-products' ) );
					}
				} );

			return () => {
				cancelled = true;
			};
		}, [ limit ] );

		return el(
			'div',
			{ className: 'wso-pp wso-pp--editor' },
			heading
				? el( 'h3', { className: 'wso-pp__heading' }, heading )
				: null,
			el(
				'div',
				{ className: 'wso-pp__toolbar' },
				el( 'span', { className: 'wso-pp__badge' }, __( 'Preview', 'wso-popular-products' ) ),
				products
					? el(
							'span',
							{ className: 'wso-pp__status' },
							products.length === 1
								? __( '1 product', 'wso-popular-products' )
								: `${ products.length } ${ __( 'products', 'wso-popular-products' ) }`
					  )
					: null
			),
			error
				? el( Notice, { status: 'error', isDismissible: false }, error )
				: null,
			products === null && ! error
				? el(
						'div',
						{ className: 'wso-pp__loading' },
						el( Spinner, {} ),
						el( 'span', {}, __( 'Loading products…', 'wso-popular-products' ) )
				  )
				: null,
			products && products.length === 0
				? el( 'p', { className: 'wso-pp__empty' }, __( 'No published products yet.', 'wso-popular-products' ) )
				: null,
			products && products.length > 0
				? el(
						'ul',
						{ className: 'wso-pp__list' },
						products.map( ( product ) =>
							el(
								'li',
								{ key: product.id, className: 'wso-pp__item' },
								el(
									'a',
									{ className: 'wso-pp__link', href: product.permalink, onClick: ( event ) => event.preventDefault() },
									showImage && product.hasImage
										? el( 'img', { className: 'wso-pp__image', src: product.image, alt: '' } )
										: null,
									el( 'span', { className: 'wso-pp__name' }, product.name )
								),
								showPrice
									? el( 'span', { className: 'wso-pp__price' }, product.price )
									: null
							)
						)
				  )
				: null
		);
	};

	registerBlockType( BLOCK_NAME, {
		attributes: {
			heading: { type: 'string', default: 'Popular products' },
			limit: { type: 'number', default: 3 },
			showPrice: { type: 'boolean', default: true },
			showImage: { type: 'boolean', default: true },
			showCurrencyToggle: { type: 'boolean', default: false },
		},

		edit: ( { attributes, setAttributes } ) => {
			const { heading, limit, showPrice, showImage, showCurrencyToggle } = attributes;
			const blockProps = useBlockProps( { className: 'wso-pp' } );

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
						el( RangeControl, {
							label: __( 'Number of products', 'wso-popular-products' ),
							value: limit,
							min: 1,
							max: 12,
							onChange: ( value ) => setAttributes( { limit: value } ),
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Display', 'wso-popular-products' ), initialOpen: true },
						el( ToggleControl, {
							label: __( 'Show price', 'wso-popular-products' ),
							checked: showPrice,
							onChange: ( value ) => setAttributes( { showPrice: value } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show image', 'wso-popular-products' ),
							checked: showImage,
							onChange: ( value ) => setAttributes( { showImage: value } ),
						} ),
						el( ToggleControl, {
							label: __( 'Let shoppers choose the currency', 'wso-popular-products' ),
							checked: showCurrencyToggle,
							onChange: ( value ) => setAttributes( { showCurrencyToggle: value } ),
							help: __( 'Adds a selector; the shopper picks the currency. The site-wide switch lives under WooCommerce → Popular Products.', 'wso-popular-products' ),
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( Preview, { heading, limit, showPrice, showImage } )
				)
			);
		},

		// Dynamic block: the front end is rendered by render.php.
		save: () => null,
	} );
} )( window.wp );
