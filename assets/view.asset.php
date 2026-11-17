<?php
/**
 * Script module metadata for assets/view.js.
 *
 * WordPress reads this file next to a script module to learn its dependencies and
 * version. It is hand-written because the plugin has no build step — a bundler would
 * generate it. Without the `@wordpress/interactivity` dependency the browser has no
 * import map entry for that specifier and the module fails to load.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

return array(
	'dependencies' => array( '@wordpress/interactivity' ),
	'version'      => '1.0.1',
);
