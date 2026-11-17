# Changelog

All notable changes to this project are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.4] — 2026-11-17

### Changed

- House coding rules applied across the plugin. No short PHP tags, no eval, no die/
  exit, no short-circuit control flow, no debug helpers, no weak hashes, no variable
  variables, no extract().
- ender.php: every decision now happens before the first ?>. The optional heading,
  image and price are built as escaped strings at the top; the markup is seven echo
  statements and no branches.
- Block attributes are sanitized and range-checked once at the top
  (sanitize_text_field, limit clamped after an is_numeric check) instead of being
  cast inline in the template.

### Added

- 	ests/Unit/CodingRulesTest.php — enforces the rules above, including an assertion that
  the template branches nowhere after the first ?>. Verified by introducing violations
  and watching the test fail.
## [1.0.3] — 2026-11-17

### Fixed

- Ordering was `total_sales DESC` only. On a store with no sales yet every product ties at
  zero, so the order was arbitrary and a newly added product could fall outside the limit.
  Now `total_sales DESC, date DESC`: sales still outrank recency once they exist, and
  otherwise the newest product appears first.
- Two blocks on one page shared one product list, because state lived in the page-global
  store. State moved into each block's `data-wp-context`; the instances are independent.
- Per-item directives read `context.showImage`, which is undefined inside `data-wp-each`
  (the item context shadows the block context), so every product image was hidden. Whether
  the `<img>` exists is now decided in PHP; per-item directives only read `context.item`.

## [1.0.2] — 2026-11-17

### Changed

- The README now states precisely what the REST API is doing here, since the data comes
  from the same site.

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

[Unreleased]: https://github.com/MaksymK-web-dev/wso-popular-products/compare/v1.0.4...HEAD
[1.0.4]: https://github.com/MaksymK-web-dev/wso-popular-products/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/MaksymK-web-dev/wso-popular-products/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/MaksymK-web-dev/wso-popular-products/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/MaksymK-web-dev/wso-popular-products/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/MaksymK-web-dev/wso-popular-products/releases/tag/v1.0.0
