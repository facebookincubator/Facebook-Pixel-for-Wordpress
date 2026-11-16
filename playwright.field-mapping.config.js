import { defineConfig, devices } from '@playwright/test';

// Admin E2E for the Form Field Mapping section, run against the latest release
// of every supported form plugin (.github/workflows/e2e-field-mapping.yml).
// Kept apart from playwright.config.js: it needs no pixel/CAPI capture, no
// customer session and no real Meta credentials.
export default defineConfig({
  testDir: './tests/e2e/admin',
  testMatch: '**/field-mapping.spec.js',
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never' }]],
  timeout: 120000,
  use: {
    ...devices['Desktop Chrome'],
    baseURL: process.env.WORDPRESS_URL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 30000,
    navigationTimeout: 60000,
  },
  projects: [{ name: 'chromium-wp-admin-field-mapping' }],
});
