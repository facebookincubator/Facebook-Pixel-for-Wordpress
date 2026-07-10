/**
 * Meta Pixel for WordPress — Pixel + CAPI event tests.
 *
 * Ported from the Meta for WooCommerce E2E suite. Validates, for every event this
 * plugin fires, that the Pixel (browser /tr) and CAPI (server, logged to disk by
 * the plugin under a test cookie) channels agree and deduplicate via a shared
 * event_id. Each event runs in both `customer` (logged-in) and `guest` modes,
 * selected by the Playwright project's metadata.userMode.
 *
 * Not covered (this plugin does not emit them): Search, ViewCategory, Subscribe.
 */

const { test, expect } = require('@playwright/test');
const {
  TIMEOUTS,
  TestSetup,
  EventValidator,
  EVENT_FIELD_CONTRACTS,
  cleanupProducts,
  installJsErrorSimulatorMuPlugin,
  removeJsErrorSimulatorMuPlugin,
  createVariableProductEventFixture,
  createGroupedProductEventFixture,
  selectVariationByLabel,
  setGroupedProductQuantity,
  loadCapturedEvents,
  getLatestEvent,
  asArray,
  assertEventContainsRetailerId,
  ignoreKnownPurchaseUserDataGap,
  getCartItemsViaStoreApi,
  clearCart,
  completeCheckoutFromCart,
  triggerAjaxAddToCartFromShop,
  isAjaxAddToCartAvailableOnShop,
  holdSignals,
  releaseSignals,
  getSignalState,
  getQueuedSignalEvents,
  submitLeadForm,
  getActiveThemeStatus,
  switchThemeBySlug,
  acquireThemeLock,
  releaseThemeLock,
} = require('./helpers/js');

// Storefront URLs seeded by tests/e2e/scripts/seed-store.sh.
const TEST_PRODUCT_URL = process.env.TEST_PRODUCT_URL || '/product/testp/';
const TEST_LEAD_FORM_URL = process.env.TEST_LEAD_FORM_URL || '/e2e-lead/';

const STOREFRONT_THEME_SLUG = 'storefront';
const THEME_PROJECT_TO_SLUG = {
  'chromium-wp-customer-classic-theme': 'twentytwentyone',
  'chromium-wp-customer-block-theme': 'twentytwentyfive',
};

let themeLockToken = null;
let originalThemeSlug = null;
let activeThemeProjectSlug = null;

function resolveThemeForProject(projectName) {
  return THEME_PROJECT_TO_SLUG[projectName] || null;
}

function userModeForProject(testInfo) {
  return testInfo?.project?.metadata?.userMode === 'guest' ? 'guest' : 'customer';
}

// Raw /tr recorder used by the signals hold/release tests (captures every hit).
function createPixelEventRequestRecorder(page, eventName) {
  const captured = [];

  const readBodyParam = (request, key) => {
    const body = request.postData() || '';
    if (!body || !body.includes('=')) return null;
    return new URLSearchParams(body).get(key);
  };

  const onRequest = (request) => {
    let parsed;
    try {
      parsed = new URL(request.url());
    } catch (_) {
      return;
    }

    const host = parsed.hostname;
    const isFacebookHost = host === 'facebook.com' || host.endsWith('.facebook.com');
    const isPixelPath = parsed.pathname === '/tr' || parsed.pathname === '/tr/';
    if (!isFacebookHost || !isPixelPath) return;

    try {
      const detectedEventName = parsed.searchParams.get('ev') || readBodyParam(request, 'ev');
      if (detectedEventName !== eventName) return;

      captured.push({
        url: request.url(),
        eventName: detectedEventName,
        eventId: parsed.searchParams.get('eid') || readBodyParam(request, 'eid') || null,
      });
    } catch (_) {
      // Ignore non-URL-safe payloads.
    }
  };

  page.on('request', onRequest);
  return {
    getEvents: () => captured.slice(),
    stop: () => page.off('request', onRequest),
  };
}

async function waitForMinimumPixelEvents(recorder, minCount, timeoutMs = 15000) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    const events = recorder.getEvents();
    if (events.length >= minCount) return events;
    await new Promise(resolve => setTimeout(resolve, 250));
  }
  return recorder.getEvents();
}

async function waitForMinimumCapiEvents(testId, eventName, minCount, timeoutMs = 15000) {
  const deadline = Date.now() + timeoutMs;
  let latest = [];
  while (Date.now() < deadline) {
    const captured = await loadCapturedEvents(testId);
    latest = captured.capi.filter(event => event.event_name === eventName);
    if (latest.length >= minCount) return latest;
    await new Promise(resolve => setTimeout(resolve, 300));
  }
  return latest;
}

async function getSignalRuntimeSnapshot(page) {
  const state = await getSignalState(page).catch(() => ({ state: null, held: null }));

  const runtime = await page.evaluate(() => {
    const signal = window.FacebookSignal || null;
    const queue = signal && Array.isArray(signal._queue) ? signal._queue : [];
    const config = signal && signal._config ? signal._config : {};

    return {
      hasFbwcsignal: Boolean(window.fbwcsignal),
      hasFacebookSignal: Boolean(signal),
      facebookSignalHeldFlag: signal ? Boolean(signal._held) : null,
      queueLength: queue.length,
      queueEventIds: queue.map(event => event && event.event_id).filter(Boolean),
      hasReleaseMethod: Boolean(signal && typeof signal.release === 'function'),
      configReleaseAction: config.releaseAction || null,
      configAjaxUrl: config.ajaxUrl || null,
      signalCookie: document.cookie.split(';').map(v => v.trim())
        .find(v => v.startsWith('wc_facebook_signals_state=')) || null,
      locationHref: window.location.href,
    };
  }).catch(() => ({}));

  return { ...state, ...runtime };
}

test.beforeEach(async ({}, testInfo) => {
  // Select the mode-appropriate field contracts (customer vs guest).
  EVENT_FIELD_CONTRACTS.setUserMode(userModeForProject(testInfo));
});

test.beforeAll(async ({}, workerInfo) => {
  const targetThemeSlug = resolveThemeForProject(workerInfo.project.name);
  if (!targetThemeSlug) return;

  themeLockToken = await acquireThemeLock();
  const status = await getActiveThemeStatus();
  originalThemeSlug = status.activeStylesheet || STOREFRONT_THEME_SLUG;

  if (status.activeStylesheet !== targetThemeSlug) {
    const switchResult = await switchThemeBySlug(targetThemeSlug);
    if (!switchResult.success) {
      throw new Error(`Failed to activate theme '${targetThemeSlug}' for project '${workerInfo.project.name}': ${switchResult.error || 'unknown error'}`);
    }
  }
  activeThemeProjectSlug = targetThemeSlug;
});

test.afterAll(async () => {
  if (!themeLockToken) return;
  try {
    const restoreTarget = originalThemeSlug || STOREFRONT_THEME_SLUG;
    const restoreResult = await switchThemeBySlug(restoreTarget);
    if (!restoreResult.success) {
      throw new Error(`Failed to restore theme '${restoreTarget}' after '${activeThemeProjectSlug || 'unknown'}': ${restoreResult.error || 'unknown error'}`);
    }
  } finally {
    await releaseThemeLock(themeLockToken);
    themeLockToken = null;
    originalThemeSlug = null;
    activeThemeProjectSlug = null;
  }
});

// -----------------------------------------------------------------------------
// PageView
// -----------------------------------------------------------------------------
test('PageView', async ({ page }, testInfo) => {
  const { testId, pixelCapture } = await TestSetup.init(page, 'PageView', testInfo);

  const eventPromise = pixelCapture.waitForEvent();
  await page.goto('/');
  await TestSetup.waitForPageReady(page);
  await eventPromise;

  const validator = new EventValidator(testId);
  await validator.checkDebugLog();
  const result = await validator.validate('PageView', page);

  TestSetup.logResult('PageView', result);
  expect(result.passed).toBe(true);
});

test('PageView with fbclid', async ({ page }, testInfo) => {
  const { testId, pixelCapture } = await TestSetup.init(page, 'PageView', testInfo);

  const fbclid = process.env.TEST_FBCLID || `e2e${Date.now()}`;
  const isBraveProject = testInfo?.project?.name?.includes('brave');

  if (isBraveProject) {
    const seededFbc = `fb.1.${Date.now()}.${fbclid}`;
    await page.context().addCookies([{ name: '_fbc', value: seededFbc, url: process.env.WORDPRESS_URL }]);
  }

  const eventPromise = pixelCapture.waitForEvent();
  await page.goto(`/?fbclid=${fbclid}`);
  await TestSetup.waitForPageReady(page);
  await eventPromise;

  const validator = new EventValidator(testId, true, false, { allowBraveFbcNormalization: isBraveProject });
  await validator.checkDebugLog();
  const result = await validator.validate('PageView', page);

  TestSetup.logResult('PageView (fbclid)', result);
  expect(result.passed).toBe(true);
});

// -----------------------------------------------------------------------------
// ViewContent
// -----------------------------------------------------------------------------
test('ViewContent', async ({ page }, testInfo) => {
  const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent', testInfo);

  const eventPromise = pixelCapture.waitForEvent();
  await page.goto(TEST_PRODUCT_URL);
  await TestSetup.waitForPageReady(page);
  await eventPromise;

  const validator = new EventValidator(testId);
  await validator.checkDebugLog();
  const result = await validator.validate('ViewContent', page);

  TestSetup.logResult('ViewContent', result);
  expect(result.passed).toBe(true);
});

test('ViewContent - Variable Product', async ({ page }, testInfo) => {
  let fixture;
  try {
    fixture = await createVariableProductEventFixture();
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent', testInfo);

    const eventPromise = pixelCapture.waitForEvent();
    await page.goto(fixture.parentUrl);
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = ignoreKnownPurchaseUserDataGap(await validator.validate('ViewContent', page));

    const captured = await loadCapturedEvents(testId);
    const pixelEvent = getLatestEvent(captured.pixel, 'ViewContent');
    const capiEvent = getLatestEvent(captured.capi, 'ViewContent');

    assertEventContainsRetailerId(pixelEvent, fixture.parentRetailerId);
    assertEventContainsRetailerId(capiEvent, fixture.parentRetailerId);
    expect(pixelEvent?.custom_data?.content_type).toBe('product_group');
    expect(capiEvent?.custom_data?.content_type).toBe('product_group');

    TestSetup.logResult('ViewContent (Variable Product)', result);
    expect(result.passed).toBe(true);
  } finally {
    if (fixture?.cleanupProductIds?.length) await cleanupProducts(fixture.cleanupProductIds);
  }
});

test('ViewContent - Grouped Product', async ({ page }, testInfo) => {
  let fixture;
  try {
    fixture = await createGroupedProductEventFixture();
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent', testInfo);

    const eventPromise = pixelCapture.waitForEvent();
    await page.goto(fixture.groupedUrl);
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = ignoreKnownPurchaseUserDataGap(await validator.validate('ViewContent', page));

    const captured = await loadCapturedEvents(testId);
    const pixelEvent = getLatestEvent(captured.pixel, 'ViewContent');
    const capiEvent = getLatestEvent(captured.capi, 'ViewContent');

    assertEventContainsRetailerId(pixelEvent, fixture.groupedRetailerId);
    assertEventContainsRetailerId(capiEvent, fixture.groupedRetailerId);
    expect(pixelEvent?.custom_data?.content_type).toBe('product_group');
    expect(capiEvent?.custom_data?.content_type).toBe('product_group');

    TestSetup.logResult('ViewContent (Grouped Product)', result);
    expect(result.passed).toBe(true);
  } finally {
    if (fixture?.cleanupProductIds?.length) await cleanupProducts(fixture.cleanupProductIds);
  }
});

// -----------------------------------------------------------------------------
// AddToCart
// -----------------------------------------------------------------------------
test('AddToCart', async ({ page }, testInfo) => {
  const { testId, pixelCapture } = await TestSetup.init(page, 'AddToCart', testInfo);

  await page.goto(TEST_PRODUCT_URL);
  await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

  const eventPromise = pixelCapture.waitForEvent();
  await page.click('.single_add_to_cart_button');
  await page.waitForLoadState('networkidle');
  await eventPromise;

  const validator = new EventValidator(testId);
  await validator.checkDebugLog();
  const result = await validator.validate('AddToCart', page);

  TestSetup.logResult('AddToCart', result);
  expect(result.passed).toBe(true);
});

test('AddToCart - AJAX (shop loop parity with PDP)', async ({ page }, testInfo) => {
  await clearCart(page);

  const baseline = await TestSetup.init(page, 'AddToCart', testInfo);
  await page.goto(TEST_PRODUCT_URL);
  await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

  const pdpEventPromise = baseline.pixelCapture.waitForEvent();
  await page.click('.single_add_to_cart_button');
  await page.waitForLoadState('networkidle');
  await pdpEventPromise;

  const baselineValidator = new EventValidator(baseline.testId);
  await baselineValidator.checkDebugLog();
  expect((await baselineValidator.validate('AddToCart', page)).passed).toBe(true);

  const baselineCaptured = await loadCapturedEvents(baseline.testId);
  const baselinePixel = getLatestEvent(baselineCaptured.pixel, 'AddToCart');
  const baselineCapi = getLatestEvent(baselineCaptured.capi, 'AddToCart');
  expect(baselinePixel).toBeTruthy();
  expect(baselineCapi).toBeTruthy();

  const baselineProductId = String(
    baselinePixel?.custom_data?.contents?.[0]?.id ||
    baselinePixel?.custom_data?.content_ids?.[0] ||
    baselineCapi?.custom_data?.contents?.[0]?.id ||
    baselineCapi?.custom_data?.content_ids?.[0] || ''
  );

  await clearCart(page);

  const ajaxRun = await TestSetup.init(page, 'AddToCart', testInfo);
  const ajaxEventPromise = ajaxRun.pixelCapture.waitForEvent();
  // Don't constrain by expectedProductId: shop-loop buttons key off the numeric
  // WC product id, whereas our content_ids carry the retailer id (sku_id). Select
  // the product by slug; parity is verified below by comparing captured content.
  const ajaxTrace = await triggerAjaxAddToCartFromShop(page, {
    productUrl: TEST_PRODUCT_URL,
  });
  await ajaxEventPromise;

  expect(ajaxTrace.usedAjax).toBe(true);
  expect(ajaxTrace.mainFrameNavigated).toBe(false);

  const ajaxValidator = new EventValidator(ajaxRun.testId);
  await ajaxValidator.checkDebugLog();
  expect((await ajaxValidator.validate('AddToCart', page)).passed).toBe(true);

  const ajaxCaptured = await loadCapturedEvents(ajaxRun.testId);
  const ajaxPixel = getLatestEvent(ajaxCaptured.pixel, 'AddToCart');
  const ajaxCapi = getLatestEvent(ajaxCaptured.capi, 'AddToCart');
  expect(ajaxPixel).toBeTruthy();
  expect(ajaxCapi).toBeTruthy();

  const pickComparable = (event) => ({
    content_ids: asArray(event?.custom_data?.content_ids),
    contents: asArray(event?.custom_data?.contents),
    content_type: event?.custom_data?.content_type,
    value: Number(event?.custom_data?.value),
    currency: event?.custom_data?.currency,
  });
  const normalizeContents = (items) => items
    .map(item => ({ id: String(item?.id), quantity: Number(item?.quantity) }))
    .sort((a, b) => `${a.id}:${a.quantity}`.localeCompare(`${b.id}:${b.quantity}`));
  const normalizeComparable = (data) => ({
    ...data,
    content_ids: data.content_ids.map(String).sort(),
    contents: normalizeContents(data.contents),
  });

  expect(normalizeComparable(pickComparable(ajaxPixel))).toEqual(normalizeComparable(pickComparable(baselinePixel)));
  expect(normalizeComparable(pickComparable(ajaxCapi))).toEqual(normalizeComparable(pickComparable(baselineCapi)));
});

test('AddToCart - Variable Product (selected variation)', async ({ page }, testInfo) => {
  let fixture;
  try {
    fixture = await createVariableProductEventFixture();
    const targetVariation = fixture.variations.find(v => v.option === 'Large') || fixture.variations[0];
    const { testId, pixelCapture } = await TestSetup.init(page, 'AddToCart', testInfo);

    await page.goto(fixture.parentUrl);
    await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

    const selected = await selectVariationByLabel(page, { attributeSlug: fixture.attributeSlug, label: targetVariation.option });
    expect(selected.variationId).toBe(Number(targetVariation.id));

    const eventPromise = pixelCapture.waitForEvent();
    await page.click('.single_add_to_cart_button');
    await page.waitForLoadState('networkidle');
    await eventPromise;

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = ignoreKnownPurchaseUserDataGap(await validator.validate('AddToCart', page));

    const captured = await loadCapturedEvents(testId);
    const pixelEvent = getLatestEvent(captured.pixel, 'AddToCart');
    const capiEvent = getLatestEvent(captured.capi, 'AddToCart');
    assertEventContainsRetailerId(pixelEvent, targetVariation.retailer_id);
    assertEventContainsRetailerId(capiEvent, targetVariation.retailer_id);

    TestSetup.logResult('AddToCart (Variable Product)', result);
    expect(result.passed).toBe(true);
  } finally {
    if (fixture?.cleanupProductIds?.length) await cleanupProducts(fixture.cleanupProductIds);
  }
});

// -----------------------------------------------------------------------------
// InitiateCheckout
// -----------------------------------------------------------------------------
test('InitiateCheckout', async ({ page }, testInfo) => {
  await clearCart(page);
  const { testId, pixelCapture } = await TestSetup.init(page, 'InitiateCheckout', testInfo);

  await page.goto(TEST_PRODUCT_URL);
  await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);
  await page.click('.single_add_to_cart_button');
  await page.waitForTimeout(TIMEOUTS.SHORT);

  const cartItems = await getCartItemsViaStoreApi(page);
  expect(cartItems.length).toBe(1);

  const eventPromise = pixelCapture.waitForEvent();
  await page.goto('/checkout');
  await TestSetup.waitForPageReady(page);
  await eventPromise;
  await page.waitForTimeout(TIMEOUTS.SHORT);

  const validator = new EventValidator(testId);
  await validator.checkDebugLog();
  const result = await validator.validate('InitiateCheckout', page);

  TestSetup.logResult('InitiateCheckout', result);
  expect(result.passed).toBe(true);
});

// -----------------------------------------------------------------------------
// Purchase (CAPI-only contract)
// -----------------------------------------------------------------------------
test('Purchase', async ({ page }, testInfo) => {
  const { testId } = await TestSetup.init(page, 'Purchase', testInfo);

  await page.goto(TEST_PRODUCT_URL);
  await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);
  await page.click('.single_add_to_cart_button');
  await page.waitForTimeout(TIMEOUTS.SHORT);

  await completeCheckoutFromCart(page);

  const validator = new EventValidator(testId);
  await validator.checkDebugLog();
  const result = ignoreKnownPurchaseUserDataGap(await validator.validate('Purchase', page));

  TestSetup.logResult('Purchase', result);
  expect(result.passed).toBe(true);
});

test('Purchase - Multiple Place Order Clicks (dedup)', async ({ page }, testInfo) => {
  const { testId } = await TestSetup.init(page, 'Purchase', testInfo);

  await page.goto(TEST_PRODUCT_URL);
  await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);
  await page.click('.single_add_to_cart_button');
  await page.waitForTimeout(TIMEOUTS.SHORT);

  await page.goto('/checkout');
  await TestSetup.waitForPageReady(page);

  for (let i = 0; i < 3; i++) {
    const editButton = page.getByRole('button', { name: /edit/i }).first();
    if (!(await editButton.isVisible({ timeout: TIMEOUTS.SHORT }).catch(() => false))) break;
    await editButton.click().catch(() => {});
    await page.waitForTimeout(TIMEOUTS.INSTANT);
  }

  const defaults = {
    email: process.env.TEST_USER_EMAIL || `e2e+${Date.now()}@example.test`,
    firstName: 'E2E', lastName: 'Customer', address1: '1 Test Street',
    city: 'London', country: 'GB', state: 'LND', postcode: 'EC1A1BB', phone: '0123456789',
  };
  const fillIfVisible = async (selector, value) => {
    const field = page.locator(selector).first();
    if (await field.isVisible({ timeout: TIMEOUTS.SHORT }).catch(() => false)) await field.fill(value);
  };
  const selectIfVisible = async (selector, value) => {
    const field = page.locator(selector).first();
    if (await field.isVisible({ timeout: TIMEOUTS.SHORT }).catch(() => false)) {
      await field.selectOption(value).catch(async () => { await field.fill(value); });
    }
  };

  await fillIfVisible('#email', defaults.email);
  for (const prefix of ['shipping', 'billing']) {
    await fillIfVisible(`#${prefix}-first_name`, defaults.firstName);
    await fillIfVisible(`#${prefix}-last_name`, defaults.lastName);
    await fillIfVisible(`#${prefix}-address_1`, defaults.address1);
    await fillIfVisible(`#${prefix}-city`, defaults.city);
    await selectIfVisible(`#${prefix}-country`, defaults.country);
    await selectIfVisible(`#${prefix}-state`, defaults.state);
    await fillIfVisible(`#${prefix}-postcode`, defaults.postcode);
    await fillIfVisible(`#${prefix}-phone`, defaults.phone);
  }

  await page.waitForSelector('.wc-block-components-radio-control__option[for="radio-control-wc-payment-method-options-cod"]', { state: 'visible', timeout: TIMEOUTS.LONG });
  await page.click('label[for="radio-control-wc-payment-method-options-cod"]');
  await page.waitForTimeout(TIMEOUTS.INSTANT);

  const termsCheckbox = page.locator('#wc-terms-and-conditions-checkbox-text').first();
  if (await termsCheckbox.isVisible({ timeout: TIMEOUTS.SHORT }).catch(() => false)) await termsCheckbox.click();

  const placeOrderButton = page.locator('.wc-block-components-checkout-place-order-button');
  await placeOrderButton.scrollIntoViewIfNeeded();
  await placeOrderButton.click();
  await page.waitForTimeout(100);
  await placeOrderButton.click({ force: true }).catch(() => {});
  await page.waitForTimeout(100);
  await placeOrderButton.click({ force: true }).catch(() => {});

  await page.waitForURL('**/checkout/order-received/**', { timeout: TIMEOUTS.EXTRA_LONG });
  await page.waitForTimeout(TIMEOUTS.NORMAL);

  const validator = new EventValidator(testId);
  await validator.checkDebugLog();
  const result = ignoreKnownPurchaseUserDataGap(await validator.validate('Purchase', page));

  TestSetup.logResult('Purchase (Deduplication)', result);
  expect(result.passed).toBe(true);
});

test('Purchase - Variable Product', async ({ page }, testInfo) => {
  let fixture;
  try {
    fixture = await createVariableProductEventFixture();
    await clearCart(page);
    const targetVariation = fixture.variations.find(v => v.option === 'Large') || fixture.variations[0];
    const { testId } = await TestSetup.init(page, 'Purchase', testInfo);

    await page.goto(fixture.parentUrl);
    await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);
    await selectVariationByLabel(page, { attributeSlug: fixture.attributeSlug, label: targetVariation.option });
    await page.click('.single_add_to_cart_button');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(TIMEOUTS.SHORT);

    const cartItems = await getCartItemsViaStoreApi(page);
    expect(cartItems.length).toBe(1);
    expect(String(cartItems[0].id)).toBe(String(targetVariation.id));

    await completeCheckoutFromCart(page);

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = ignoreKnownPurchaseUserDataGap(await validator.validate('Purchase', page));

    const captured = await loadCapturedEvents(testId);
    const capiPurchase = getLatestEvent(captured.capi, 'Purchase');
    // WooCommerce order items report get_product_id() (the PARENT) for variations,
    // so the plugin emits the parent retailer id in the Purchase content_ids.
    assertEventContainsRetailerId(capiPurchase, fixture.parentRetailerId);

    TestSetup.logResult('Purchase (Variable Product)', result);
    expect(result.passed).toBe(true);
  } finally {
    if (fixture?.cleanupProductIds?.length) await cleanupProducts(fixture.cleanupProductIds);
  }
});

test('Purchase - Grouped Product', async ({ page }, testInfo) => {
  let fixture;
  try {
    fixture = await createGroupedProductEventFixture();
    await clearCart(page);
    const child = fixture.children[0];
    const { testId } = await TestSetup.init(page, 'Purchase', testInfo);

    await page.goto(fixture.groupedUrl);
    await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);
    await setGroupedProductQuantity(page, child.id, 1);
    await page.click('.single_add_to_cart_button');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(TIMEOUTS.SHORT);

    const cartItems = await getCartItemsViaStoreApi(page);
    expect(cartItems.length).toBe(1);
    expect(String(cartItems[0].id)).toBe(String(child.id));

    await completeCheckoutFromCart(page);

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = ignoreKnownPurchaseUserDataGap(await validator.validate('Purchase', page));

    const captured = await loadCapturedEvents(testId);
    const capiPurchase = getLatestEvent(captured.capi, 'Purchase');
    assertEventContainsRetailerId(capiPurchase, child.retailer_id);

    TestSetup.logResult('Purchase (Grouped Product)', result);
    expect(result.passed).toBe(true);
  } finally {
    if (fixture?.cleanupProductIds?.length) await cleanupProducts(fixture.cleanupProductIds);
  }
});

// -----------------------------------------------------------------------------
// Lead (Contact Form 7) — dual-channel (Pixel + CAPI) sharing one event_id.
// -----------------------------------------------------------------------------
test('Lead', async ({ page }, testInfo) => {
  const { testId, pixelCapture } = await TestSetup.init(page, 'Lead', testInfo);

  const eventPromise = pixelCapture.waitForEvent();
  await submitLeadForm(page, TEST_LEAD_FORM_URL, {
    email: process.env.TEST_LEAD_EMAIL || `lead+${Date.now()}@example.test`,
  });
  await eventPromise;
  await page.waitForTimeout(TIMEOUTS.SHORT);

  const validator = new EventValidator(testId);
  await validator.checkDebugLog();
  const result = await validator.validate('Lead', page);

  TestSetup.logResult('Lead', result);
  expect(result.passed).toBe(true);
});

// -----------------------------------------------------------------------------
// Signals consent hold / release
// -----------------------------------------------------------------------------
test('ViewContent - Signals held (no immediate Pixel/CAPI send)', async ({ page }, testInfo) => {
  try {
    await page.context().clearCookies();
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent', testInfo, true);

    await page.goto('/');
    await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

    const holdResult = await holdSignals(page);
    expect(holdResult.state).toBe('held');

    const eventPromise = pixelCapture.waitForEvent();
    await page.goto(TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId, false, true);
    await validator.checkDebugLog();
    const result = await validator.validate('ViewContent', page);

    const queued = await getQueuedSignalEvents(page, 'ViewContent');
    expect(queued.length).toBeGreaterThanOrEqual(1);

    TestSetup.logResult('ViewContent (Signals Held)', result);
    expect(result.passed).toBe(true);
  } finally {
    await releaseSignals(page).catch(() => {});
  }
});

test('ViewContent - Signals release flushes queued Pixel/CAPI', async ({ page }, testInfo) => {
  await page.context().clearCookies();
  const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent', testInfo);

  await page.goto('/');
  await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

  const holdResult = await holdSignals(page);
  expect(holdResult.state).toBe('held');

  const recorder = createPixelEventRequestRecorder(page, 'ViewContent');
  try {
    await page.goto(TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page);

    const queuedBeforeRelease = await getQueuedSignalEvents(page, 'ViewContent');
    expect(queuedBeforeRelease.length).toBeGreaterThanOrEqual(1);

    const replayPromise = pixelCapture.waitForEvent();
    const releaseResult = await releaseSignals(page);
    await replayPromise;
    expect(releaseResult.state).toBe('active');

    const releasedPixel = await waitForMinimumPixelEvents(recorder, queuedBeforeRelease.length, 15000);
    const releasedCapi = await waitForMinimumCapiEvents(testId, 'ViewContent', queuedBeforeRelease.length, 15000);
    expect(releasedPixel.length).toBeGreaterThanOrEqual(queuedBeforeRelease.length);
    expect(releasedCapi.length).toBeGreaterThanOrEqual(queuedBeforeRelease.length);

    const queuedIds = new Set(queuedBeforeRelease.map(e => e.event_id).filter(Boolean));
    const replayedPixelIds = new Set(releasedPixel.map(e => e.eventId).filter(Boolean));
    const replayedCapiIds = new Set(releasedCapi.map(e => e.event_id).filter(Boolean));
    queuedIds.forEach((eventId) => {
      expect(replayedPixelIds.has(eventId)).toBe(true);
      expect(replayedCapiIds.has(eventId)).toBe(true);
    });

    expect((await getQueuedSignalEvents(page, 'ViewContent')).length).toBe(0);
  } finally {
    recorder.stop();
    await releaseSignals(page).catch(() => {});
  }
});

test('AddToCart - Signals hold/release with multiple shop AJAX clicks', async ({ page }, testInfo) => {
  const ajaxAvailable = await isAjaxAddToCartAvailableOnShop(page, { productUrl: TEST_PRODUCT_URL });
  test.skip(!ajaxAvailable, 'Shop AJAX AddToCart is not available in this browser/theme fixture.');

  await clearCart(page);
  await page.context().clearCookies();
  const { testId } = await TestSetup.init(page, 'AddToCart', testInfo);

  await page.goto('/shop');
  await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

  const holdResult = await holdSignals(page);
  expect(holdResult.state).toBe('held');

  // Reload in held mode so FacebookSignal boots with held=true + release config.
  await page.reload({ waitUntil: 'networkidle' });
  await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

  const snapshotAfterHold = await getSignalRuntimeSnapshot(page);
  expect(snapshotAfterHold.hasFacebookSignal).toBe(true);
  expect(snapshotAfterHold.configReleaseAction).toBe('fbpix_release_signals');

  const recorder = createPixelEventRequestRecorder(page, 'AddToCart');
  try {
    const targetClicks = 3;
    const shopAjaxButtons = page.locator('a.add_to_cart_button.ajax_add_to_cart, button.add_to_cart_button.ajax_add_to_cart');
    const totalButtons = await shopAjaxButtons.count();
    test.skip(totalButtons < targetClicks, `Need at least ${targetClicks} AJAX add-to-cart buttons on /shop. Found ${totalButtons}.`);

    for (let index = 0; index < targetClicks; index += 1) {
      await shopAjaxButtons.nth(index).click({ force: true });
      await page.waitForTimeout(TIMEOUTS.NORMAL);
      await page.waitForLoadState('networkidle').catch(() => {});
    }

    expect(recorder.getEvents().length).toBe(0);

    const queuedBeforeRelease = await getQueuedSignalEvents(page, 'AddToCart');
    expect(queuedBeforeRelease.length).toBeGreaterThanOrEqual(targetClicks);
    const queuedEventIds = queuedBeforeRelease.map(e => e.event_id).filter(Boolean);
    expect(new Set(queuedEventIds).size).toBe(queuedEventIds.length);

    const releaseResult = await releaseSignals(page);
    expect(releaseResult.state).toBe('active');
    await page.waitForTimeout(1000);

    const releasedPixel = await waitForMinimumPixelEvents(recorder, queuedBeforeRelease.length, 20000);
    const releasedCapi = await waitForMinimumCapiEvents(testId, 'AddToCart', queuedBeforeRelease.length, 20000);
    expect(releasedCapi.length).toBeGreaterThan(0);

    const releasedCapiIds = new Set(releasedCapi.map(e => e.event_id).filter(Boolean));
    releasedCapiIds.forEach((eventId) => expect(queuedEventIds.includes(eventId)).toBe(true));

    // Each released CAPI event_id appears exactly once (dedup preserved on replay).
    const grouped = releasedCapi.reduce((acc, e) => {
      const id = e.event_id || `missing-${acc.size}`;
      acc.set(id, (acc.get(id) || 0) + 1);
      return acc;
    }, new Map());
    grouped.forEach((count) => expect(count).toBe(1));

    expect((await getQueuedSignalEvents(page, 'AddToCart')).length).toBe(0);

    const cookies = await page.context().cookies();
    expect(cookies.find(c => c.name === '_fbp')).toBeDefined();
  } finally {
    recorder.stop();
    await releaseSignals(page).catch(() => {});
    await clearCart(page).catch(() => {});
  }
});

// -----------------------------------------------------------------------------
// Isolated execution (pixel still fires despite other plugins' JS errors)
// -----------------------------------------------------------------------------
test('ViewContent - Isolated Execution (with JS errors from other plugins)', async ({ page }, testInfo) => {
  await installJsErrorSimulatorMuPlugin();
  try {
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent', testInfo);

    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error' || msg.text().includes('[E2E Test]')) consoleErrors.push(msg.text());
    });

    const eventPromise = pixelCapture.waitForEvent();
    await page.goto(TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page);
    await eventPromise;

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('ViewContent', page);

    TestSetup.logResult('ViewContent (Isolated Execution)', result);
    expect(result.passed).toBe(true);
  } finally {
    await removeJsErrorSimulatorMuPlugin();
  }
});

test('AddToCart - Isolated Execution (with JS errors from other plugins)', async ({ page }, testInfo) => {
  await installJsErrorSimulatorMuPlugin();
  try {
    const { testId, pixelCapture } = await TestSetup.init(page, 'AddToCart', testInfo);

    await page.goto(TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page, TIMEOUTS.INSTANT);

    const eventPromise = pixelCapture.waitForEvent();
    await page.click('.single_add_to_cart_button');
    await page.waitForLoadState('networkidle');
    await eventPromise;

    const validator = new EventValidator(testId);
    await validator.checkDebugLog();
    const result = await validator.validate('AddToCart', page);

    TestSetup.logResult('AddToCart (Isolated Execution)', result);
    expect(result.passed).toBe(true);
  } finally {
    await removeJsErrorSimulatorMuPlugin();
  }
});

// -----------------------------------------------------------------------------
// Privacy Sandbox
// -----------------------------------------------------------------------------
function getMajorBrowserVersion(versionString) {
  const match = String(versionString || '').match(/^(\d+)/);
  return match ? parseInt(match[1], 10) : NaN;
}

test('Privacy Sandbox - Topics API available in Chromium', async ({ page, browser }) => {
  test.skip(!test.info().project.name.includes('privacy-sandbox'), 'Privacy Sandbox test only runs on privacy-sandbox project');
  const chromiumMajor = getMajorBrowserVersion(browser.version());
  test.skip(Number.isNaN(chromiumMajor) || chromiumMajor < 115, `Privacy Sandbox requires Chrome/Chromium >= 115 (detected ${browser.version()})`);

  await page.goto('/');
  await TestSetup.waitForPageReady(page);

  const topicsResult = await page.evaluate(async () => {
    const hasTopicsApi = typeof document !== 'undefined' && typeof document.browsingTopics === 'function';
    if (!hasTopicsApi) return { hasTopicsApi, topicsCount: null, error: null };
    try {
      const topics = await document.browsingTopics();
      return { hasTopicsApi, topicsCount: Array.isArray(topics) ? topics.length : 0, error: null };
    } catch (error) {
      return { hasTopicsApi, topicsCount: null, error: String(error?.message || error) };
    }
  });

  expect(topicsResult.hasTopicsApi).toBe(true);
  expect(topicsResult.error).toBeNull();
  expect(typeof topicsResult.topicsCount).toBe('number');
});

test('Privacy Sandbox - Protected Audience API shape in Chromium', async ({ page, browser }) => {
  test.skip(!test.info().project.name.includes('privacy-sandbox'), 'Privacy Sandbox test only runs on privacy-sandbox project');
  const chromiumMajor = getMajorBrowserVersion(browser.version());
  test.skip(Number.isNaN(chromiumMajor) || chromiumMajor < 115, `Privacy Sandbox requires Chrome/Chromium >= 115 (detected ${browser.version()})`);

  await page.goto('/');
  await TestSetup.waitForPageReady(page);

  const apiShape = await page.evaluate(() => ({
    hasNavigator: typeof navigator !== 'undefined',
    hasJoinAdInterestGroup: typeof navigator?.joinAdInterestGroup === 'function',
    hasRunAdAuction: typeof navigator?.runAdAuction === 'function',
    hasLeaveAdInterestGroup: typeof navigator?.leaveAdInterestGroup === 'function',
  }));

  const hasAnyProtectedAudienceApi =
    apiShape.hasJoinAdInterestGroup || apiShape.hasRunAdAuction || apiShape.hasLeaveAdInterestGroup;

  expect(apiShape.hasNavigator).toBe(true);
  expect(hasAnyProtectedAudienceApi).toBe(true);
});
