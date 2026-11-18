/**
 * Runs each block's edit.js against stubbed WordPress globals and asserts it registers.
 *
 * Run: node --test tests/js
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInContext } from 'node:vm';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import { makeBrowserContext } from './wp-stubs.mjs';

const here = dirname( fileURLToPath( import.meta.url ) );
const pluginRoot = join( here, '..', '..' );

/**
 * Blocks under test: directory, registered name, and the attributes block.json promises.
 */
const BLOCKS = [
	{
		dir: 'popular-products',
		name: 'wso/popular-products',
		attributes: {
			heading: 'Popular products',
			limit: 3,
			showPrice: true,
			showImage: true,
			showCurrencyToggle: false,
		},
	},
	{
		dir: 'nbu-rates',
		name: 'wso/nbu-rates',
		attributes: {
			heading: 'Exchange rates',
			codes: 'USD,EUR,PLN',
			showDate: true,
		},
	},
];

/**
 * Executes one block's edit.js against the stubs.
 *
 * @param {string} dir Block directory name.
 * @return {Array<Object>} Registration calls.
 */
const runEditorScript = ( dir ) => {
	const source = readFileSync( join( pluginRoot, 'blocks', dir, 'edit.js' ), 'utf8' );
	const { context, registered } = makeBrowserContext();

	runInContext( source, context, { filename: `${ dir }/edit.js` } );

	return registered;
};

/**
 * Invokes every function component found in an element tree.
 *
 * `edit()` only describes its tree; the components run later, in the editor.
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

for ( const block of BLOCKS ) {
	test( `${ block.dir }: registers exactly one block`, () => {
		assert.equal( runEditorScript( block.dir ).length, 1 );
	} );

	test( `${ block.dir }: registers the namespace block.json declares`, () => {
		const [ registration ] = runEditorScript( block.dir );

		assert.equal( registration.name, block.name );
	} );

	test( `${ block.dir }: attributes match the block.json contract`, () => {
		const [ { settings } ] = runEditorScript( block.dir );

		for ( const [ key, expected ] of Object.entries( block.attributes ) ) {
			assert.equal(
				settings.attributes[ key ]?.default,
				expected,
				`${ block.dir }: attribute "${ key }" default drifted from block.json.`
			);
		}
	} );

	test( `${ block.dir }: save() writes nothing into post content`, () => {
		const [ { settings } ] = runEditorScript( block.dir );

		assert.equal( typeof settings.save, 'function' );
		assert.equal( settings.save(), null, 'a dynamic block must not save static markup' );
	} );

	test( `${ block.dir }: edit() returns an element tree`, () => {
		const [ { settings } ] = runEditorScript( block.dir );

		const tree = settings.edit( {
			attributes: { ...block.attributes },
			setAttributes: () => {},
		} );

		assert.ok( tree, 'edit() must return something renderable' );
	} );

	test( `${ block.dir }: every component in the editor tree renders`, () => {
		const [ { settings } ] = runEditorScript( block.dir );

		const tree = settings.edit( {
			attributes: { ...block.attributes },
			setAttributes: () => {},
		} );

		assert.doesNotThrow( () => renderComponents( tree ) );
	} );
}
