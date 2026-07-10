/**
 * Event field contracts for Pixel + Conversions API (CAPI).
 *
 * Ported from the Meta for WooCommerce E2E suite and adapted for Meta Pixel for
 * WordPress. Key differences from the source:
 *
 * - Only events this plugin actually fires are covered: PageView, ViewContent,
 *   AddToCart, InitiateCheckout, Purchase (WooCommerce integration) and Lead
 *   (form integration). Search / ViewCategory / Subscribe are NOT emitted by this
 *   plugin and are intentionally omitted.
 * - `Lead` is DUAL-channel here (Pixel + CAPI). In the WooCommerce plugin it was
 *   Pixel-only; in this plugin the form integrations call
 *   FacebookServerSideEvent::track() (CAPI) AND ship the Pixel code back in the
 *   AJAX response, sharing one event_id.
 * - Contracts are mode-aware (logged-in `customer` vs `guest`). Guests have no
 *   session-derived PII, so the base user_data requirement is relaxed to the only
 *   matching keys guaranteed without a logged-in user (fbp + client IP/UA on CAPI).
 *   Event-specific user_data overrides (Lead) still apply in both modes because
 *   that PII comes from the submitted form, not the session.
 * - The plugin-metadata `custom_data` base is intentionally empty: the exact
 *   Pixel custom_data envelope keys differ from the WooCommerce plugin, so we only
 *   assert the content fields that this plugin demonstrably sets.
 *
 * The validator reads this module as `EVENT_FIELD_CONTRACTS[eventName]`. To keep
 * the validator untouched, this module is a Proxy that returns the contract table
 * for the currently-selected user mode. Call `setUserMode('guest'|'customer')`
 * (exposed on the exported object) before validating in a given mode.
 */

// -----------------------------------------------------------------------------
// Generic parameter categories
// -----------------------------------------------------------------------------
// Calibrated to what this plugin actually emits:
// - Pixel advanced matching emits em + fbp (not ct/zp/cn/external_id).
// - CAPI emits em + fbp; external_id / client_ip_address are not guaranteed
//   (client_ip_address is absent for localhost/CI), so they aren't required.
// The em hash is still asserted to MATCH across channels (validator), so this
// remains a meaningful check.
const USER_DATA = {
  customer: {
    pixel: ['em', 'fbp'],
    capi: ['em', 'fbp'],
  },
  // Guests carry no session PII; only the browser id is guaranteed.
  guest: {
    pixel: ['fbp'],
    capi: ['fbp'],
  },
};

// Plugin-metadata custom_data envelope is not asserted (see file header).
const CUSTOM_DATA_BASE = { pixel: [], capi: [] };

// -----------------------------------------------------------------------------
// Event-level overlays (only event-specific deltas)
// -----------------------------------------------------------------------------
const EVENT_OVERLAYS = {
  // PageView is dual-channel: with CAPI integration on (default) and PageView not
  // in the events filter (default), OpenBridge forwards the browser PageView to
  // CAPI server-side with a shared event_id for dedup.
  PageView: {
    channels: ['pixel', 'capi'],
    custom_data: { pixel: [], capi: [] },
  },

  ViewContent: {
    channels: ['pixel', 'capi'],
    custom_data: {
      pixel: ['content_ids', 'content_type', 'content_name', 'value', 'currency'],
      capi: ['content_ids', 'content_type', 'content_name', 'value', 'currency'],
    },
  },

  AddToCart: {
    channels: ['pixel', 'capi'],
    custom_data: {
      pixel: ['content_ids', 'content_type', 'value', 'currency'],
      capi: ['content_ids', 'content_type', 'value', 'currency'],
    },
  },

  InitiateCheckout: {
    channels: ['pixel', 'capi'],
    custom_data: {
      pixel: ['content_ids', 'content_type', 'num_items', 'value', 'currency', 'contents'],
      capi: ['content_ids', 'content_type', 'num_items', 'value', 'currency', 'contents'],
    },
  },

  Purchase: {
    channels: ['capi'],
    custom_data: {
      capi: ['content_ids', 'content_type', 'value', 'currency', 'contents'],
    },
  },

  Lead: {
    channels: ['pixel', 'capi'],
    // Form-provided PII: present for both logged-in and guest submitters.
    user_data: {
      pixel: ['em', 'fbp'],
      capi: ['em', 'fbp'],
    },
    custom_data: { pixel: [], capi: [] },
  },
};

function unique(values) {
  return [...new Set(values)];
}

function buildEventContract(overlay, mode) {
  const contract = { channels: overlay.channels };

  for (const channel of overlay.channels) {
    const baseUserData = USER_DATA[mode][channel] || [];

    const userDataOverlay = overlay.user_data?.[channel];
    const userData = userDataOverlay ? unique(userDataOverlay) : unique(baseUserData);

    const customDataOverlay = overlay.custom_data?.[channel] || [];
    const customData = unique([...CUSTOM_DATA_BASE[channel], ...customDataOverlay]);

    contract[channel] = { user_data: userData, custom_data: customData };
  }

  return contract;
}

function buildTable(mode) {
  return Object.fromEntries(
    Object.entries(EVENT_OVERLAYS).map(([eventName, overlay]) => [
      eventName,
      buildEventContract(overlay, mode),
    ])
  );
}

const TABLES = {
  customer: buildTable('customer'),
  guest: buildTable('guest'),
};

let currentMode = 'customer';

function setUserMode(mode) {
  currentMode = mode === 'guest' ? 'guest' : 'customer';
}

// Proxy so `EVENT_FIELD_CONTRACTS[eventName]` resolves against the active mode,
// while `EVENT_FIELD_CONTRACTS.setUserMode(...)` remains callable.
module.exports = new Proxy(
  {},
  {
    get(_target, prop) {
      if (prop === 'setUserMode') return setUserMode;
      if (prop === '__esModule') return false;
      return TABLES[currentMode][prop];
    },
    has(_target, prop) {
      return prop === 'setUserMode' || prop in TABLES[currentMode];
    },
    ownKeys() {
      return Reflect.ownKeys(TABLES[currentMode]);
    },
    getOwnPropertyDescriptor(_target, prop) {
      if (prop in TABLES[currentMode]) {
        return { configurable: true, enumerable: true, value: TABLES[currentMode][prop] };
      }
      return undefined;
    },
  }
);
