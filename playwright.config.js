import { defineConfig, devices } from '@playwright/test';

// Meta Pixel for WordPress fires storefront pixel/CAPI events for non-admin
// visitors only, so every project runs the storefront events spec as either a
// logged-in customer or a guest. There is no admin project (unlike the source
// WooCommerce suite, which also drives catalog-sync admin flows).
const EVENTS_SPEC = '**/events-test.spec.js';

const commonTimeouts = {
  actionTimeout: 180000,
  navigationTimeout: 180000,
};

const CUSTOMER_STORAGE = './tests/e2e/.auth/customer.json';

const privacySandboxOrigin = process.env.WORDPRESS_URL
  ? (() => {
      try {
        return new URL(process.env.WORDPRESS_URL).origin;
      } catch {
        return null;
      }
    })()
  : null;

const privacySandboxArgs = [
  '--enable-features=BrowsingTopics,InterestGroupStorage,AdInterestGroupAPI,Fledge,RunAdAuction,PrivacySandboxAdsAPIsOverride',
  '--enable-blink-features=BrowsingTopics,InterestGroupStorage,AdInterestGroupAPI,RunAdAuction',
  '--test-third-party-cookie-phaseout',
  ...(privacySandboxOrigin ? [`--unsafely-treat-insecure-origin-as-secure=${privacySandboxOrigin}`] : []),
];

const customerUse = {
  ...devices['Desktop Chrome'],
  ...commonTimeouts,
  storageState: CUSTOMER_STORAGE,
};

// Guests get a fresh context (no stored auth).
const guestUse = {
  ...devices['Desktop Chrome'],
  ...commonTimeouts,
};

const edgeExecutablePath = process.env.EDGE_EXECUTABLE_PATH;
const firefoxExecutablePath = process.env.FIREFOX_EXECUTABLE_PATH;
const braveExecutablePath = process.env.BRAVE_EXECUTABLE_PATH;
const operaExecutablePath = process.env.OPERA_EXECUTABLE_PATH;
const requireRealEdge = process.env.REQUIRE_REAL_EDGE === '1';
const requireRealFirefox = process.env.REQUIRE_REAL_FIREFOX === '1';
const requireRealBrave = process.env.REQUIRE_REAL_BRAVE === '1';
const requireRealOpera = process.env.REQUIRE_REAL_OPERA === '1';

if (requireRealEdge && !edgeExecutablePath) {
  throw new Error('REQUIRE_REAL_EDGE=1 but EDGE_EXECUTABLE_PATH is not set. Refusing channel fallback.');
}
if (requireRealFirefox && firefoxExecutablePath) {
  throw new Error('REQUIRE_REAL_FIREFOX=1 uses Playwright Firefox channel. Do not set FIREFOX_EXECUTABLE_PATH.');
}
if (requireRealBrave && !braveExecutablePath) {
  throw new Error('REQUIRE_REAL_BRAVE=1 but BRAVE_EXECUTABLE_PATH is not set. Refusing Chromium fallback.');
}
if (requireRealOpera && !operaExecutablePath) {
  throw new Error('REQUIRE_REAL_OPERA=1 but OPERA_EXECUTABLE_PATH is not set. Refusing Chromium fallback.');
}

export default defineConfig({
  testDir: './tests/e2e',
  testMatch: '**/tests/e2e/**/*.spec.js',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: 'html',
  timeout: 1000000,
  globalSetup: './tests/e2e/global-setup.js',
  use: {
    baseURL: process.env.WORDPRESS_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    ignoreHTTPSErrors: true,
    ...commonTimeouts,
  },

  projects: [
    // -------------------------
    // Baseline chromium: both user modes exercise every event.
    // -------------------------
    {
      name: 'chromium-wp-customer',
      testMatch: [EVENTS_SPEC],
      use: customerUse,
      metadata: { userMode: 'customer' },
    },
    {
      name: 'chromium-wp-guest',
      testMatch: [EVENTS_SPEC],
      use: guestUse,
      metadata: { userMode: 'guest' },
    },

    // -------------------------
    // Theme coverage (customer).
    // -------------------------
    {
      name: 'chromium-wp-customer-classic-theme',
      testMatch: [EVENTS_SPEC],
      use: customerUse,
      metadata: { userMode: 'customer', theme: 'twentytwentyone' },
    },
    {
      name: 'chromium-wp-customer-block-theme',
      testMatch: [EVENTS_SPEC],
      use: customerUse,
      metadata: { userMode: 'customer', theme: 'twentytwentyfive' },
    },

    // -------------------------
    // Privacy Sandbox (customer, real Chrome channel).
    // -------------------------
    {
      name: 'chromium-privacy-sandbox-wp-customer',
      testMatch: [EVENTS_SPEC],
      use: {
        ...customerUse,
        channel: 'chrome',
        launchOptions: { args: privacySandboxArgs },
      },
      metadata: { userMode: 'customer' },
    },

    // -------------------------
    // Cross-browser (customer).
    // -------------------------
    {
      name: 'edge-wp-customer',
      testMatch: [EVENTS_SPEC],
      use: {
        ...customerUse,
        ...(edgeExecutablePath
          ? { launchOptions: { executablePath: edgeExecutablePath } }
          : { channel: 'msedge' }),
      },
      metadata: { userMode: 'customer' },
    },
    {
      name: 'firefox-wp-customer',
      testMatch: [EVENTS_SPEC],
      use: {
        ...devices['Desktop Firefox'],
        ...commonTimeouts,
        ...(requireRealFirefox
          ? { channel: 'firefox' }
          : firefoxExecutablePath
            ? { launchOptions: { executablePath: firefoxExecutablePath } }
            : {}),
        storageState: CUSTOMER_STORAGE,
      },
      metadata: { userMode: 'customer' },
    },
    {
      name: 'brave-wp-customer',
      testMatch: [EVENTS_SPEC],
      use: {
        ...customerUse,
        ...(requireRealBrave
          ? { launchOptions: { executablePath: braveExecutablePath } }
          : { userAgent: `${devices['Desktop Chrome'].userAgent} Brave/1.0.0.0` }),
      },
      metadata: { userMode: 'customer' },
    },
    {
      name: 'opera-wp-customer',
      testMatch: [EVENTS_SPEC],
      use: {
        ...customerUse,
        ...(requireRealOpera
          ? { launchOptions: { executablePath: operaExecutablePath } }
          : { userAgent: `${devices['Desktop Chrome'].userAgent} OPR/100.0.0.0` }),
      },
      metadata: { userMode: 'customer' },
    },

    // -------------------------
    // Mobile (customer).
    // -------------------------
    {
      name: 'android-pixel-wp-customer',
      testMatch: [EVENTS_SPEC],
      use: {
        ...devices['Pixel 5'],
        ...commonTimeouts,
        storageState: CUSTOMER_STORAGE,
      },
      metadata: { userMode: 'customer' },
    },
    {
      name: 'safari-ios-wp-customer',
      testMatch: [EVENTS_SPEC],
      use: {
        ...devices['iPhone 13'],
        ...commonTimeouts,
        browserName: 'webkit',
        storageState: CUSTOMER_STORAGE,
      },
      metadata: { userMode: 'customer' },
    },
  ],
});
