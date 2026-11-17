# Changelog

All notable changes to this project are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.1] — 2026-11-17

### Fixed

- The editor preview threw on `wp.apiFetch` and the block rendered *"This block has
  encountered an error and cannot be previewed"*: the package is a function, not an object,
  so destructuring it produced `undefined`.
- The editor script depended on nothing, because `assets/edit.asset.php` did not exist.
  WordPress therefore loaded `edit.js` before `wp.blocks`, registration failed in the
  browser, and the block never appeared in the inserter.

### Changed

- Plugin folder, text domain and repository renamed to `wso-popular-products`. The previous
  name collided with an unrelated plugin on WordPress.org, which made the admin offer an
  "update" that would have overwritten this plugin's files.

## [1.0.0] — 2026-11-17

### Added

- `wso/popular-products` block registered from `block.json` (apiVersion 3), rendered
  server-side by `render.php` with Interactivity API directives.
- `view.js`: fetches the plugin's REST route on mount and patches the DOM — refreshing the
  list never reloads the page.
- `edit.js`: editor UI with a live preview from the same endpoint, plus inspector controls
  for heading, count, price and image.
- REST route `GET /wp-json/wso-popular-products/v1/products`, with `limit` validated to
  1–12, results cached in a transient for five minutes, and an empty list instead of an
  error when WooCommerce is unavailable.
- Prices formatted with `wc_price()` and HTML-decoded once, so they render as text rather
  than `&nbsp;` entities.

[Unreleased]: https://github.com/MaksymK-web-dev/wso-popular-products/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/MaksymK-web-dev/wso-popular-products/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/MaksymK-web-dev/wso-popular-products/releases/tag/v1.0.0
