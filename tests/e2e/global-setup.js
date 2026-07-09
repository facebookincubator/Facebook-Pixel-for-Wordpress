const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { TIMEOUTS } = require('./helpers/js');
const { execWP } = require('./helpers/js/wordpress/exec');

const baseURL = process.env.WORDPRESS_URL;
const customerUsername = process.env.WP_CUSTOMER_USERNAME;

/**
 * Establish a logged-in customer session without a UI login by generating a real
 * WordPress auth cookie server-side (via WP-CLI) and injecting it into a browser
 * context. This avoids the flaky storefront form login entirely. The resulting
 * storageState is used by all the "customer" projects; "guest" projects use a
 * fresh context with no storageState.
 */
async function saveCustomerAuthViaWpCli(browser, authPath) {
  console.log('\n📋 Seeding CUSTOMER session via WP-CLI (no UI login)...');

  const php = `
    $user = get_user_by('login', ${JSON.stringify(customerUsername)});
    if (!$user) { echo 'NO_USER:' . ${JSON.stringify(customerUsername)}; return; }
    $expiration = time() + 2 * DAY_IN_SECONDS;
    $token = WP_Session_Tokens::get_instance($user->ID)->create($expiration);
    echo json_encode([
      'name' => 'wordpress_logged_in_' . COOKIEHASH,
      'value' => wp_generate_auth_cookie($user->ID, $expiration, 'logged_in', $token),
      'expiration' => $expiration,
    ]);
  `;

  const { stdout } = await execWP(php);
  const start = stdout.indexOf('{');
  const end = stdout.lastIndexOf('}');
  if (start === -1 || end === -1) {
    throw new Error(`Failed to generate customer auth cookie via WP-CLI. Output: ${stdout.trim()}`);
  }
  const { name, value, expiration } = JSON.parse(stdout.slice(start, end + 1));

  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  await context.addCookies([{
    name,
    value,
    domain: new URL(baseURL).hostname,
    path: '/',
    httpOnly: true,
    secure: false,
    expires: expiration,
  }]);

  const page = await context.newPage();
  await page.goto(`${baseURL}/`, { waitUntil: 'domcontentloaded', timeout: TIMEOUTS.MAX });
  await page.locator('body.logged-in').waitFor({ state: 'attached', timeout: TIMEOUTS.MAX });

  await context.storageState({ path: authPath });
  console.log(`✅ Customer state saved to ${authPath}`);
  await context.close();
}

async function globalSetup() {
  const customerAuthPath = './tests/e2e/.auth/customer.json';
  fs.mkdirSync(path.dirname(customerAuthPath), { recursive: true });

  // Ensure the captured-events directory exists and is writable by the container.
  const capturedDir = path.join(__dirname, 'captured-events');
  fs.mkdirSync(capturedDir, { recursive: true });

  // Bump auto-increment to random high numbers so each run creates products and
  // terms with unique IDs that won't collide with stale Facebook catalog data.
  const startId = Math.floor(Math.random() * 900000) + 100000;
  const termStartId = Math.floor(Math.random() * 900000) + 100000;
  try {
    await execWP(`global \\$wpdb; \\$wpdb->query('ALTER TABLE ' . \\$wpdb->posts . ' AUTO_INCREMENT = ${startId}');`);
    await execWP(`global \\$wpdb; \\$wpdb->query('ALTER TABLE ' . \\$wpdb->terms . ' AUTO_INCREMENT = ${termStartId}');`);
    await execWP(`global \\$wpdb; \\$wpdb->query('ALTER TABLE ' . \\$wpdb->term_taxonomy . ' AUTO_INCREMENT = ${termStartId}');`);
    console.log(`🔢 AUTO_INCREMENT set (posts=${startId}, terms=${termStartId})`);
  } catch (err) {
    console.warn(`⚠️ Could not set AUTO_INCREMENT (is wp-env running?): ${err.message}`);
  }

  if (fs.existsSync(customerAuthPath)) {
    console.log('✅ Customer auth file already exists - skipping login setup');
    return;
  }

  if (!baseURL || !customerUsername) {
    // Allow guest-only runs without customer creds: write an empty state so the
    // customer projects can still load (they will effectively behave as guests).
    console.warn('⚠️ WORDPRESS_URL or WP_CUSTOMER_USERNAME not set — writing empty customer state.');
    fs.writeFileSync(customerAuthPath, JSON.stringify({ cookies: [], origins: [] }));
    return;
  }

  const browser = await chromium.launch();
  try {
    console.log('🔐 Global Setup: authenticating customer...');
    await saveCustomerAuthViaWpCli(browser, customerAuthPath);
    console.log('🎉 Global Setup complete.');
  } catch (error) {
    console.error('❌ Global Setup failed:', error.message);
    throw error;
  } finally {
    await browser.close();
  }
}

module.exports = globalSetup;
