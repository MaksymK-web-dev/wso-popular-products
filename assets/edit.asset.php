<?php
/**
 * Script metadata for assets/edit.js.
 *
 * WordPress reads this file next to a block's editor script to learn its dependencies.
 * Without it the script is registered with an empty dependency list, so none of the
 * `wp.*` globals it uses are loaded first — `wp.blocks` is undefined, registration throws,
 * and although the block exists on the server it never reaches the client-side registry,
 * which is exactly what the inserter reads. The symptom is not an error message; the block
 * simply is not in the list.
 *
 * Hand-written because the plugin has no build step. A bundler would generate it.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

return array(
	'dependencies' => array(
		'wp-api-fetch',
		'wp-block-editor',
		'wp-blocks',
		'wp-components',
		'wp-element',
		'wp-i18n',
	),
	'version'      => '1.0.1',
);
