/**
 * Runs assets/edit.js with stubbed WordPress globals and asserts it registers the block.
 *
 * This catches the failure mode that has no error message: when the editor script is
 * enqueued without its dependencies, `wp.blocks` is undefined, the script throws, and the
 * block quietly disappears from the inserter. Running the file with the globals present
 * proves the registration call itself is well-formed.
 *
 * Run: node --test tests/js
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createContext, runInContext } from 'node:vm';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname( fileURLToPath( import.meta.url ) );
const source = readFileSync( join( here, '..', '..', 'assets', 'edit.js' ), 'utf8' );

/**
 * Minimal stand-ins for the WordPress packages the editor script destructures at load.
 *
 * @return {Object} A `wp` global plus a record of what was registered.
 */
const makeGlobals = () => {
	const registered = [];

	const component = ( name ) => {
		const Stub = ( props ) => props?.children ?? null;
		Stub.displayName = name;
		return Stub;
	};

	const wp = {
		blocks: {
			registerBlockType: ( name, settings ) => registered.push( { name, settings } ),
		},
		element: {
			createElement: ( type, props, ...children ) => ( { type, props, children } ),
			Fragment: 'Fragment',
			// Runs the effect immediately. In the editor its body is where the fetch lives,
			// and a stub that skips it would hide exactly the errors this file exists to catch.
			useEffect: ( callback ) => {
				const cleanup = callback();
				if ( typeof cleanup === 'function' ) {
					cleanup();
				}
			},
			useState: ( initial ) => [ initial, () => {} ],
		},
		blockEditor: {
			InspectorControls: component( 'InspectorControls' ),
			useBlockProps: ( extra = {} ) => ( { className: extra.className ?? '' } ),
		},
		components: {
			PanelBody: component( 'PanelBody' ),
			RangeControl: component( 'RangeControl' ),
			TextControl: component( 'TextControl' ),
			ToggleControl: component( 'ToggleControl' ),
			Spinner: component( 'Spinner' ),
			Notice: component( 'Notice' ),
		},
		i18n: {
			__: ( text ) => text,
		},
		// Like the real package: a callable with helpers attached, not an object.
		apiFetch: Object.assign( ( options ) => Promise.resolve( [] ), {
			createNonceMiddleware: () => () => {},
			use: () => {},
		} ),
	};

	return { wp, registered };
};

/**
 * Executes edit.js against the stubs.
 *
 * @return {Array<Object>} Registration calls.
 */
const runEditorScript = () => {
	const { wp, registered } = makeGlobals();
	const context = createContext( { wp, window: { location: { href: 'https://example.test/' } }, console } );

	runInContext( source, context, { filename: 'edit.js' } );

	return registered;
};

test( 'registers exactly one block', () => {
	const registered = runEditorScript();

	assert.equal( registered.length, 1 );
} );

test( 'registers the namespace declared in block.json', () => {
	const [ registration ] = runEditorScript();

	assert.equal( registration.name, 'wso/popular-products' );
} );

test( 'declares the attributes the block.json contract promises', () => {
	const [ { settings } ] = runEditorScript();
	const { attributes } = settings;

	assert.equal( attributes.heading.default, 'Popular products' );
	assert.equal( attributes.limit.default, 3 );
	assert.equal( attributes.showPrice.default, true );
	assert.equal( attributes.showImage.default, true );
} );

test( 'provides edit and save, with save producing no static markup', () => {
	const [ { settings } ] = runEditorScript();

	assert.equal( typeof settings.edit, 'function' );
	assert.equal( typeof settings.save, 'function' );
	assert.equal( settings.save(), null, 'a dynamic block must save nothing into post content' );
} );

test( 'edit() returns an element tree rather than throwing', () => {
	const [ { settings } ] = runEditorScript();

	const tree = settings.edit( {
		attributes: {
			heading: 'Popular products',
			limit: 3,
			showPrice: true,
			showImage: true,
		},
		setAttributes: () => {},
	} );

	assert.ok( tree, 'edit() must return something renderable' );
} );

/**
 * Invokes every function component found in an element tree.
 *
 * `edit()` only *describes* its tree; the components inside it run later, in the editor.
 * That gap is exactly where a runtime error hides — the block renders a red
 * "This block has encountered an error and cannot be previewed" box instead of failing a
 * test. Executing the components here closes it.
 *
 * @param {*} node Element, element list or primitive.
 * @param {number} depth Guard against runaway recursion.
 * @return {*} Whatever the component returned, or the node unchanged.
 */
const renderComponents = ( node, depth = 0 ) => {
	if ( depth > 6 || node === null || typeof node !== 'object' ) {
		return node;
	}

	if ( Array.isArray( node ) ) {
		return node.map( ( child ) => renderComponents( child, depth + 1 ) );
	}

	// Our createElement stub produces { type, props, children }.
	if ( typeof node.type === 'function' ) {
		const rendered = node.type( node.props ?? {} );
		return renderComponents( rendered, depth + 1 );
	}

	if ( node.children ) {
		node.children = renderComponents( node.children, depth + 1 );
	}

	return node;
};

test( 'every component in the editor tree renders without throwing', () => {
	const [ { settings } ] = runEditorScript();

	const tree = settings.edit( {
		attributes: {
			heading: 'Popular products',
			limit: 3,
			showPrice: true,
			showImage: true,
		},
		setAttributes: () => {},
	} );

	assert.doesNotThrow( () => renderComponents( tree ) );
} );
