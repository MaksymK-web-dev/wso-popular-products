<?php
/**
 * PHPUnit bootstrap.
 *
 * The tests cover block metadata and the plugin's file layout — the parts that fail
 * silently at runtime. No WordPress install is loaded, so the suite runs anywhere.
 *
 * @package WSO\PopularProducts
 */

declare( strict_types = 1 );

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoload.php';
