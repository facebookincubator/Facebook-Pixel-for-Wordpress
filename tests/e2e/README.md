# Pixel / CAPI E2E tests

Playwright end-to-end tests that validate the plugin's **Pixel** and **Conversions
API (CAPI)** events together, and assert they deduplicate via a shared `event_id`.
Ported from the Meta for WooCommerce E2E suite.

## How it works

- **Pixel channel** — Playwright intercepts browser requests to `facebook.com/tr`
  and writes them to `tests/e2e/helpers/captured-events/pixel-<testId>.json`
  (`helpers/js/events/capture.js`).
- **CAPI channel** — the plugin logs each transformed server event to
  `tests/e2e/helpers/captured-events/capi-<testId>.json` when a per-test cookie is present.
  The hook lives in `core/class-facebookserversideevent.php::maybe_log_events_for_tests()`,
  invoked from `core/signals/class-capisenderbase.php::send_request()` (the CAPI
  send choke point), and is gated on a non-production environment +
  the `FB_E2E_TEST_COOKIE_NAME` / `FB_E2E_LOGGER_PATH` env values. It is a strict
  no-op in production.
- A per-test cookie (`facebook_test_id`) ties both files together; the validator
  (`helpers/js/events/validator.js`) asserts counts, field contracts, timestamp
  proximity, `fbp`/`fbc`, SHA-256 PII, and **`pixel.event_id === capi.event_id`**.

Each event runs in two modes via Playwright projects: `customer` (logged-in) and
`guest`. Contracts relax user-data requirements for guests
(`helpers/js/events/field-contracts.js` → `setUserMode`).

## Requirements

Barebones WordPress (no Docker) — the same model CI uses: WordPress + WooCommerce
+ Contact Form 7 + this plugin, served over HTTP, with WP-CLI available.

- A WordPress install (e.g. Local by Flywheel, or the CI's WordPress-tarball +
  `php -S` + WP-CLI + MySQL). Point `WORDPRESS_PATH` at it and `WORDPRESS_URL` at
  its URL.
- WooCommerce + Contact Form 7 active; the standalone **facebook-for-woocommerce**
  plugin must NOT be active (it suppresses this plugin's commerce events).
- Node 18–22 (Playwright can hang extracting browsers on Node 24).
- A real Meta **pixel id** and **CAPI access token** — CAPI only logs on a
  successful send, so fake credentials produce no CAPI events.

## Running locally

```bash
composer install
npm install
npx playwright install --with-deps chromium

export WORDPRESS_PATH="/path/to/wordpress"     # the WP install root
export WORDPRESS_URL="http://localhost:10003"  # its URL
export FB_PIXEL_ID=...                          # real pixel id
export FB_ACCESS_TOKEN=...                      # real CAPI token

npm run env:seed                 # store setup, pixel config, customer, product, CF7 form
npm run test:e2e -- --project=chromium-wp-customer
npm run test:e2e -- --project=chromium-wp-guest
npm run test:e2e:report
```

`WP_CUSTOMER_USERNAME`/`WP_CUSTOMER_PASSWORD` default to `customer`/`customerpass`
(the customer is created by `env:seed`). See `.env.example` for all variables.

## CI

`.github/workflows/e2e-tests.yml` stands up a barebones WordPress (WordPress
tarball + `php -S` + WP-CLI against a MySQL service — no Docker) and runs every
browser/theme project. It reads `FB_PIXEL_ID` and `FB_ACCESS_TOKEN` from the
**QA** environment (optionally `TEST_FBCLID`); without them the job is skipped.

## Events covered

PageView (+ fbclid), ViewContent (+ variable/grouped), AddToCart (+ AJAX,
+ variable), InitiateCheckout, Purchase (+ dedup, + variable/grouped), Lead (CF7,
dual-channel), signals hold/release, isolated execution, Privacy Sandbox.

Not covered — this plugin does not emit them: Search, ViewCategory, Subscribe.
