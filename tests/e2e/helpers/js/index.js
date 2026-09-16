/**
 * E2E Test Helpers - Barrel Export
 *
 * Location: tests/e2e/helpers/js/index.js
 *
 * Re-exports the helper modules used by the Meta Pixel for WordPress event suite.
 * (WooCommerce catalog-sync helpers — products/categories/plugin/batch-monitor —
 * are not ported.)
 */

const { TIMEOUTS } = require('./constants/timeouts');

const exec = require('./wordpress/exec');
const plugins = require('./wordpress/plugins');
const themes = require('./wordpress/themes');

const login = require('./auth/login');
const purchase = require('./checkout/purchase');

const logging = require('./utils/logging');
const errors = require('./utils/errors');
const ui = require('./utils/ui');

const EventValidator = require('./events/validator');
const PixelCapture = require('./events/capture');
const EVENT_FIELD_CONTRACTS = require('./events/field-contracts');
const TestSetup = require('./events/setup');
const productTypes = require('./events/product-types');
const runtime = require('./events/runtime');
const ajaxCart = require('./events/ajax-cart');
const signals = require('./events/signals');
const leadForm = require('./events/lead-form');

const events = {
  EventValidator,
  PixelCapture,
  EVENT_FIELD_CONTRACTS,
  EVENT_SCHEMAS: EVENT_FIELD_CONTRACTS,
  TestSetup,
  ...productTypes,
  ...runtime,
  ...ajaxCart,
  ...signals,
  ...leadForm,
};

const wordpress = { ...exec, ...plugins, ...themes };
const checkout = { ...purchase };
const utils = { ...logging, ...errors, ...ui };
const auth = { ...login };

module.exports = {
  // Constants
  TIMEOUTS,

  // Flat exports (destructuring-friendly)
  ...auth,
  ...wordpress,
  ...checkout,
  ...utils,
  ...events,

  // Grouped namespaces
  auth,
  wordpress,
  checkout,
  utils,
  events,
};
