/**
 * Editor behaviour.
 *
 * Written against `wp.element` and `wp.components` directly — no JSX, no build step.
 * That is a deliberate trade: the block ships as readable source that works from a git
 * clone, and the only thing a reviewer needs to run it is the plugin folder.
 *
 * block.json stays the source of truth for the server: attributes are declared there for
 * rendering and REST validation. The editor needs the same schema at registration time,
 * so the attribute list below mirrors it — the one duplication in this plugin, and the
 * reason it is worth a comment rather than a build pipeline.
 */

const { registerBlockType } = wp.blocks;
const { createElement: el, Fragment, useEffect, useState } = wp.element;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { PanelBody, RangeControl, TextControl, ToggleControl, Spinner, Notice } = wp.components;
const { __ } = wp.i18n;
const { apiFetch } = wp.apiFetch;

const NAMESPACE = 'wso/popular-products';

/**
 * Preview list shown in the editor.
 *
 * Uses the plugin's own REST endpoint, so what the editor shows is what the front end
 * will fetch — not a mock.
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
			path: `/wso-popular-products/v1/products?limit=${ limit }`,
		} )
			.then( ( result ) => {
				if ( ! cancelled ) {
					setProducts( Array.isArray( result ) ? result : [] );
				}
			} )
			.catch( ( reason ) => {
				if ( ! cancelled ) {
					setError( reason?.message || __( 'Could not load products.', 'popular-products-block' ) );
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
			el( 'span', { className: 'wso-pp__badge' }, __( 'Preview', 'popular-products-block' ) ),
			products
				? el(
						'span',
						{ className: 'wso-pp__status' },
						products.length === 1
							? __( '1 product', 'popular-products-block' )
							: `${ products.length } ${ __( 'products', 'popular-products-block' ) }`
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
					el( 'span', {}, __( 'Loading products…', 'popular-products-block' ) )
			  )
			: null,
		products && products.length === 0
			? el( 'p', { className: 'wso-pp__empty' }, __( 'No published products yet.', 'popular-products-block' ) )
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
								? el( 'span', { className: 'wso-pp__price', dangerouslySetInnerHTML: { __html: product.price } } )
								: null
						)
					)
			  )
			: null
	);
};

registerBlockType( NAMESPACE, {
	attributes: {
		heading: { type: 'string', default: 'Popular products' },
		limit: { type: 'number', default: 3 },
		showPrice: { type: 'boolean', default: true },
		showImage: { type: 'boolean', default: true },
	},

	edit: ( { attributes, setAttributes } ) => {
		const { heading, limit, showPrice, showImage } = attributes;
		const blockProps = useBlockProps( { className: 'wso-pp' } );

		return el(
			Fragment,
			{},
			el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Content', 'popular-products-block' ), initialOpen: true },
					el( TextControl, {
						label: __( 'Heading', 'popular-products-block' ),
						value: heading,
						onChange: ( value ) => setAttributes( { heading: value } ),
						help: __( 'Leave empty to hide the heading.', 'popular-products-block' ),
					} ),
					el( RangeControl, {
						label: __( 'Number of products', 'popular-products-block' ),
						value: limit,
						min: 1,
						max: 12,
						onChange: ( value ) => setAttributes( { limit: value } ),
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Display', 'popular-products-block' ), initialOpen: true },
					el( ToggleControl, {
						label: __( 'Show price', 'popular-products-block' ),
						checked: showPrice,
						onChange: ( value ) => setAttributes( { showPrice: value } ),
					} ),
					el( ToggleControl, {
						label: __( 'Show image', 'popular-products-block' ),
						checked: showImage,
						onChange: ( value ) => setAttributes( { showImage: value } ),
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
