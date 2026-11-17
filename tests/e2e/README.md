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
- PHP 8.3 or newer for the E2E environment when installing the latest Contact
  Form 7. The plugin's PHP 7.4 minimum remains covered by the separate unit-test
  matrix.
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
browser/theme project against the declared and latest WordPress releases. The
current declared stack pins Contact Form 7 to 6.1.7 (requires WordPress >= 6.7);
CF7 6.2 requires WordPress >= 7.1 and is exercised on the latest stack instead.
The E2E PHP runtime is 8.3 to support the latest CF7 fixture; this does not change
the plugin's PHP minimum or its separate PHP 7.4 unit-test coverage. It reads
`FB_PIXEL_ID` and `FB_ACCESS_TOKEN` from the
**QA** environment (optionally `TEST_FBCLID`); without them the job is skipped.

## Events covered

PageView (+ fbclid), ViewContent (+ variable/grouped), AddToCart (+ AJAX,
+ variable), InitiateCheckout, Purchase (+ dedup, + variable/grouped), Lead (CF7,
dual-channel), signals hold/release, isolated execution, Privacy Sandbox.
The Privacy Sandbox project verifies Pixel/CAPI PageView delivery when Topics
is absent or rejects as removed, rather than requiring a retired browser API.

Not covered — this plugin does not emit them: Search, ViewCategory, Subscribe.

## Form Field Mapping admin tests

`tests/e2e/admin/field-mapping.spec.js` drives the Form Field Mapping section as
an admin, against the latest release of every supported form plugin (Contact
Form 7, WPForms Lite, Ninja Forms, Formidable Forms, Mailchimp for WP). For each
form it checks that the table lists it once with its id and that its title
links to the plugin's own editor: the link must match the edit link on the
plugin's forms list (or the plugin's edit-link helper) and open the editor for
that form. A form plugin release that changes its editor URL fails this spec.
It also covers the editor popup and saved mappings of deleted forms, and the
Lead event quality panel. The seed installs a test mu-plugin
(`tests/e2e/fixtures/mu-plugins/e2e-dataset-quality.php`) that answers the
panel's Dataset Quality API requests with what the spec sets, so Meta is never
called; don't seed a site you use for anything else.

It has its own workflow (`.github/workflows/e2e-field-mapping.yml`, on PRs and
every Monday) and config (`playwright.field-mapping.config.js`), and needs no
Meta credentials. To run it locally, with the five form plugins active:

```bash
export WORDPRESS_PATH="/path/to/wordpress" WORDPRESS_URL="http://localhost:10003"
export WP_USERNAME=admin WP_PASSWORD=...           # an administrator
export FIELD_MAPPING_FORMS_FILE=/tmp/field-mapping-forms.json

npm run env:seed:field-mapping   # placeholder connection, one form per plugin, test mu-plugin
npm run test:e2e:field-mapping
```

> **Use a disposable test site.** The seed changes the site's data:
>
> - It creates one form per plugin, and they are not removed.
> - It adds a mapping for a deleted Contact Form 7 form (id 999999) to the
>   saved mappings.
> - When the plugin is not connected, it fills placeholder credentials into
>   the empty connection settings. These make the site look connected (with a
>   pixel id that is not yours) until you disconnect.
>
> An existing connection, either connection flow, and other saved mappings
> are left as they are. The spec itself saves and resets mappings of the
> forms it uses.
