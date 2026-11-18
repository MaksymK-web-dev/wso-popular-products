/**
 * Stubs for the WordPress packages an editor script destructures at load.
 *
 * Shared by the editor tests so a change to what the blocks expect from `wp` is made
 * once. Mirrors the real packages closely enough that a script which works here also
 * loads in the editor.
 */

import { createContext } from 'node:vm';

/**
 * Builds a fresh `wp` global plus a record of what was registered through it.
 *
 * @return {{ wp: Object, registered: Array<Object> }} Globals and registration log.
 */
export const makeGlobals = () => {
	const registered = [];

	/**
	 * A pass-through component stub.
	 *
	 * @param {string} name Component name, recorded on the stub.
	 * @return {Function} Component.
	 */
	const component = ( name ) => {
		const Stub = ( props ) => props?.children ?? null;
		Stub.displayName = name;
		return Stub;
	};

	const wp = {
		blocks: {
			registerBlockType: ( name, settings ) => registered.push( { name, settings } ),
			getBlockTypes: () => [],
		},
		element: {
			createElement: ( type, props, ...children ) => ( { type, props, children } ),
			Fragment: 'Fragment',
			// Runs the effect immediately: its body is where the fetch lives.
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
			SelectControl: component( 'SelectControl' ),
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
 * Builds the browser-shaped global object the scripts run against.
 *
 * `window.wp` is what an editor script reads; the bare `wp` binding exists as well
 * because the real page has both.
 *
 * @return {{ context: Object, registered: Array<Object> }} VM context and registration log.
 */
export const makeBrowserContext = () => {
	const { wp, registered } = makeGlobals();

	return {
		registered,
		context: createContext( {
			wp,
			window: { wp, location: { href: 'https://example.test/' } },
			document: { querySelectorAll: () => [], addEventListener: () => {} },
			console,
		} ),
	};
};
