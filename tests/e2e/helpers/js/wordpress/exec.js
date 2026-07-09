/**
 * WordPress execution helpers for E2E tests.
 *
 * Unlike the Meta for WooCommerce suite (which shells out to a local `php -r`
 * against a Local by Flywheel install), Meta Pixel for WordPress runs its E2E
 * environment under @wordpress/env (Docker). Every WP-CLI / PHP call is therefore
 * routed through `wp-env run cli wp ...`, executed from the repo root where
 * `.wp-env.json` lives.
 */

const { exec } = require('child_process');
const { promisify } = require('util');
const path = require('path');

const execAsync = promisify(exec);

// tests/e2e/helpers/js/wordpress/exec.js -> repo root is five levels up.
const REPO_ROOT = path.resolve(__dirname, '../../../../..');

// Large PHP evals / debug logs can produce a lot of output.
const MAX_BUFFER = 32 * 1024 * 1024;

// Kept for API compatibility with the source barrel; unused under wp-env.
const wpSitePath = process.env.WORDPRESS_PATH || null;

/**
 * Run a raw `wp` command inside the wp-env "cli" container.
 *
 * @param {string} args - Arguments passed to `wp` (already shell-safe).
 * @returns {Promise<{stdout: string, stderr: string}>}
 */
async function runWpCli(args) {
  const command = `npx wp-env run --quiet cli wp ${args}`;
  return execAsync(command, { cwd: REPO_ROOT, maxBuffer: MAX_BUFFER });
}

/**
 * Execute arbitrary PHP inside a fully-booted WordPress.
 *
 * The PHP is base64-encoded and handed to `wp eval` so we never have to worry
 * about shell/quote escaping of the payload (base64 is [A-Za-z0-9+/=] only and
 * contains no spaces, so it survives the host -> docker -> container hops).
 * `wp eval` bootstraps WordPress, so callers get $wpdb, plugin classes, etc.
 *
 * @param {string} phpCode - PHP source to run (no surrounding <?php).
 * @returns {Promise<{stdout: string, stderr: string}>}
 */
async function execWP(phpCode) {
  // Legacy callers escape dollars (e.g. \$wpdb) to survive shell interpolation.
  // With base64 eval we normalize those back to valid PHP variables.
  const normalizedCode = String(phpCode).replace(/\\\$/g, '$');
  const encoded = Buffer.from(normalizedCode, 'utf8').toString('base64');
  return runWpCli(`eval "eval(base64_decode('${encoded}'));"`);
}

/**
 * Ensure the plugin is in a testable state: pixel configured and CAPI enabled.
 * (Debug mode is controlled via WP_DEBUG in .wp-env.json.)
 *
 * @returns {Promise<boolean>}
 */
async function ensureDebugModeEnabled() {
  try {
    const { stdout } = await execWP(
      `echo \\FacebookPixelPlugin\\Core\\FacebookWordpressOptions::get_active_pixel_id();`
    );
    const pixelId = stdout.replace(/[^0-9]/g, '');
    if (!pixelId) {
      console.warn('⚠️ No pixel id configured — did env:seed run with FB_PIXEL_ID set?');
      return false;
    }
    return true;
  } catch (error) {
    console.error(`❌ Error checking plugin state: ${error.message}`);
    return false;
  }
}

/**
 * Read the WordPress debug log (WP_DEBUG_LOG) from inside the container.
 *
 * @returns {Promise<string>} Log contents, or '' when the log is absent.
 */
async function getDebugLog() {
  try {
    const { stdout } = await execAsync(
      `npx wp-env run --quiet cli bash -c "cat wp-content/debug.log 2>/dev/null || true"`,
      { cwd: REPO_ROOT, maxBuffer: MAX_BUFFER }
    );
    return stdout;
  } catch {
    return '';
  }
}

module.exports = {
  wpSitePath,
  REPO_ROOT,
  runWpCli,
  execWP,
  ensureDebugModeEnabled,
  getDebugLog,
};
