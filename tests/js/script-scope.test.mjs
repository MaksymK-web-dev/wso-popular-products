/**
 * Guard against editor scripts colliding in the shared global scope.
 *
 * `block.json` registers `editorScript` as a classic script, so the `edit.js` of every
 * block runs in one global scope on the editor page. A top-level `const` in one file
 * makes the next one fail to parse — the block then never registers and the editor
 * shows "This block has encountered an error and cannot be previewed".
 *
 * These tests load the real files, in the order the editor would, against one shared
 * context. Run: node --test tests/js
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { createContext, runInContext } from 'node:vm';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import { makeBrowserContext } from './wp-stubs.mjs';

const here = dirname( fileURLToPath( import.meta.url ) );
const pluginRoot = join( here, '..', '..' );

/**
 * Every block directory that ships an editor script.
 *
 * Discovered rather than listed, so a block added later is covered without editing
 * this file.
 *
 * @return {string[]} Directory names.
 */
const blockDirectories = () =>
	readdirSync( join( pluginRoot, 'blocks' ) )
		.filter( ( entry ) => statSync( join( pluginRoot, 'blocks', entry ) ).isDirectory() )
		.filter( ( entry ) => {
			try {
				return statSync( join( pluginRoot, 'blocks', entry, 'edit.js' ) ).isFile();
			} catch ( error ) {
				return false;
			}
		} )
		.sort();

/**
 * Source of one block's editor script.
 *
 * @param {string} dir Block directory.
 * @return {string} File contents.
 */
const editorSource = ( dir ) => readFileSync( join( pluginRoot, 'blocks', dir, 'edit.js' ), 'utf8' );

test( 'at least two blocks ship an editor script', () => {
	assert.ok( blockDirectories().length >= 2, 'expected the plugin to ship more than one block' );
} );

test( 'every editor script registers its own block when loaded together', () => {
	const { context, registered } = makeBrowserContext();

	for ( const dir of blockDirectories() ) {
		// The editor loads one classic script after another into the same scope; a
		// rethrow here is the bug this test exists for.
		assert.doesNotThrow(
			() => runInContext( editorSource( dir ), context, { filename: `${ dir }/edit.js` } ),
			`${ dir }/edit.js threw while loading in a shared scope`
		);
	}

	const names = registered.map( ( entry ) => entry.name );

	assert.equal( registered.length, blockDirectories().length, `registered ${ names.join( ', ' ) }` );
	assert.equal( new Set( names ).size, names.length, 'a block name was registered twice' );
} );

test( 'editor scripts are registered in either load order', () => {
	const order = blockDirectories();
	const reversed = [ ...order ].reverse();

	const { context, registered } = makeBrowserContext();

	for ( const dir of reversed ) {
		assert.doesNotThrow(
			() => runInContext( editorSource( dir ), context, { filename: `${ dir }/edit.js` } ),
			`${ dir }/edit.js threw when loaded last`
		);
	}

	assert.equal( registered.length, order.length );
	assert.deepEqual(
		registered.map( ( entry ) => entry.name ).sort(),
		order.map( ( dir ) => `wso/${ dir }` ).sort()
	);
} );

/**
 * Names an editor script used to declare at the top level. A declaration in the global
 * lexical scope is what made the second script fail to parse.
 *
 * @return {string[]} Identifiers.
 */
const LEAKED_NAMES = [
	'registerBlockType',
	'createElement',
	'el',
	'Fragment',
	'useEffect',
	'useState',
	'InspectorControls',
	'useBlockProps',
	'PanelBody',
	'TextControl',
	'ToggleControl',
	'RangeControl',
	'SelectControl',
	'Spinner',
	'Notice',
	'apiFetch',
	'__',
	'Preview',
	'parseCodes',
	'NAMESPACE',
];

test( 'no editor script leaks a binding into the global scope', () => {
	for ( const dir of blockDirectories() ) {
		const { context } = makeBrowserContext();

		runInContext( editorSource( dir ), context, { filename: `${ dir }/edit.js` } );

		for ( const name of LEAKED_NAMES ) {
			assert.equal(
				runInContext( `typeof ${ name }`, context ),
				'undefined',
				`${ dir }/edit.js declares "${ name }" globally; wrap the file in an IIFE.`
			);
		}
	}
} );
