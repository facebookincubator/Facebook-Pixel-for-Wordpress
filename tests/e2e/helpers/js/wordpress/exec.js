/**
 * WordPress execution helpers for E2E tests.
 *
 * The E2E environment is a barebones WordPress install (WordPress tarball +
 * `php -S` + WP-CLI against a MySQL service) — the same model the CI workflow
 * sets up, and locally a Local by Flywheel / any WP install pointed at by
 * WORDPRESS_PATH. All WP-CLI / PHP calls run against that local install; there is
 * no Docker/wp-env involved.
 */

const { exec } = require('child_process');
const { promisify } = require('util');
const fs = require('fs');

const execAsync = promisify(exec);

const wpSitePath = process.env.WORDPRESS_PATH;

// Large PHP evals / debug logs can produce a lot of output.
const MAX_BUFFER = 32 * 1024 * 1024;

function requireSitePath() {
  if (!wpSitePath) {
    throw new Error('WORDPRESS_PATH is not set');
  }
  return wpSitePath;
}

/**
 * Execute arbitrary PHP inside a fully-booted WordPress.
 *
 * The PHP is base64-encoded and eval'd after loading wp-load.php, so callers get
 * $wpdb, plugin classes, etc. Base64 avoids all shell/quote escaping of the
 * payload.
 *
 * @param {string} phpCode - PHP source to run (no surrounding <?php).
 * @returns {Promise<{stdout: string, stderr: string}>}
 */
async function execWP(phpCode) {
  const sitePath = requireSitePath();
  // Legacy callers escape dollars (e.g. \$wpdb) to survive shell interpolation.
  // With base64 eval we normalize those back to valid PHP variables.
  const normalizedCode = String(phpCode).replace(/\\\$/g, '$');
  const encoded = Buffer.from(normalizedCode, 'utf8').toString('base64');
  const command = `php -r "require_once('${sitePath}/wp-load.php'); eval(base64_decode('${encoded}'));"`;
  return execAsync(command, { cwd: __dirname, maxBuffer: MAX_BUFFER });
}

/**
 * Run a raw `wp` (WP-CLI) command against the local install.
 *
 * @param {string} args - Arguments passed to `wp` (already shell-safe).
 * @returns {Promise<{stdout: string, stderr: string}>}
 */
async function runWpCli(args) {
  const sitePath = requireSitePath();
  const wp = process.env.WP_CLI_PATH || 'wp';
  const command = `${wp} ${args} --path="${sitePath}" --allow-root`;
  return execAsync(command, { cwd: __dirname, maxBuffer: MAX_BUFFER });
}

/**
 * Confirm the plugin is configured with a pixel id (i.e. seeding ran).
 *
 * @returns {Promise<boolean>}
 */
async function ensureDebugModeEnabled() {
  try {
    const { stdout } = await execWP(
      `echo \\FacebookPixelPlugin\\Core\\FacebookWordpressOptions::get_active_pixel_id();`
    );
    if (!stdout.replace(/[^0-9]/g, '')) {
      console.warn('⚠️ No pixel id configured — did the seed step run with FB_PIXEL_ID set?');
      return false;
    }
    return true;
  } catch (error) {
    console.error(`❌ Error checking plugin state: ${error.message}`);
    return false;
  }
}

/**
 * Read the WordPress debug log (WP_DEBUG_LOG).
 *
 * @returns {string} Log contents, or '' when absent.
 */
function getDebugLog() {
  const p = process.env.WP_DEBUG_LOG;
  if (!p) return '';
  try {
    return fs.readFileSync(p, 'utf8');
  } catch {
    return '';
  }
}

module.exports = {
  wpSitePath,
  execWP,
  runWpCli,
  ensureDebugModeEnabled,
  getDebugLog,
};
