# Changelog

All notable changes to this project are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] — 2026-11-18

### Added

- **The shopper picks the currency, not the editor.** The toolbar button is replaced by a
  selector listing the store currency plus USD, EUR and PLN; the block remembers no target
  of its own, so `convertTo` is gone from `block.json`.
- **Site-wide switch** under *WooCommerce → Popular Products*. Clearing it removes the
  selector from every block on the next request, without editing a post. A block shows the
  selector only when the price is visible, the block opts in, and the site-wide switch is on.
- `Config\Settings`: the `wso_popular_products` option, with unknown keys dropped.
- A scope test that loads every `edit.js` into one global context, in both load orders, and
  asserts no editor script leaks a binding into that scope.

### Changed

- All rates for a block are fetched in one request (`codes=USD,EUR,PLN`) instead of one
  request per conversion.
- `tools/verify-toggle.mjs` drives the selector instead of the removed button.

### Fixed

- **The editor could not preview one of the two blocks.** `edit.js` files are classic
  scripts, so both ran in one global scope; each declared `const registerBlockType`,
  `const Preview` and friends at the top level, and the second file to load failed to parse
  with `SyntaxError: Identifier 'registerBlockType' has already been declared`. Only one
  block registered, and the editor showed *this block has encountered an error and cannot
  be previewed*. Both files are now IIFEs.
- `SettingsPage` printed the AI key summary under the bot token field.

## [1.1.0] — 2026-11-17

### Added

- **NBU Exchange Rates block** (wso/nbu-rates): today's hryvnia rates for a configurable
  list of currencies, read from the National Bank of Ukraine's public API.
- **Currency toggle on product cards** (showCurrencyToggle, convertTo): a button in the
  toolbar switches every price in the block to USD, EUR or PLN at the official NBU rate and
  back. The button is disabled while no rate is available.
- Rates\RateProvider: normalizes the NBU payload, caches it for six hours, and serves the
  last good answer (with stale: true) when the bank's API is unreachable.
- Rates\RatesController: GET /wso-popular-products/v1/rates?codes=USD,EUR, capped at 12
  codes per request, invalid codes dropped rather than rejected.
- Support\Logger for the WooCommerce log.
- 	ools/verify-toggle.mjs: drives headless Chrome over the DevTools Protocol, clicks the
  toggle and asserts the prices change and come back.
- Tests: metadata contract now runs per block from the same list BlockRegistrar
  registers; CodingRulesTest gained 	estDataFilesDoNotExit after an exit guard in an
  asset file silently truncated the suite to seven tests.

### Changed

- Blocks moved into locks/<name>/ with their assets side by side, the convention
  @wordpress/scripts produces. BlockRegistrar registers an explicit list.
- The products payload gained priceValue and currency, so the browser never parses a
  localised price string.
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

[Unreleased]: https://github.com/MaksymK-web-dev/wso-popular-products/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/MaksymK-web-dev/wso-popular-products/compare/v1.0.4...v1.1.0
[1.0.4]: https://github.com/MaksymK-web-dev/wso-popular-products/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/MaksymK-web-dev/wso-popular-products/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/MaksymK-web-dev/wso-popular-products/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/MaksymK-web-dev/wso-popular-products/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/MaksymK-web-dev/wso-popular-products/releases/tag/v1.0.0
