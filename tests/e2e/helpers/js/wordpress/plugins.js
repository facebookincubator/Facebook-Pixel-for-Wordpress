/**
 * Plugin / mu-plugin management helpers for E2E tests.
 *
  * The WordPress filesystem is local (barebones install), but mu-plugins are
  * still written/removed via `wp eval`
  * (file_put_contents into WPMU_PLUGIN_DIR) so the path resolves inside WP.
 */

const { execWP } = require('./exec');

async function writeMuPlugin(filename, phpContent) {
  const b64 = Buffer.from(phpContent, 'utf8').toString('base64');
  const php = `
    $dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
    if ( ! is_dir( $dir ) ) { wp_mkdir_p( $dir ); }
    file_put_contents( $dir . '/' . ${JSON.stringify(filename)}, base64_decode('${b64}') );
    echo 'MU_WRITTEN';
  `;
  await execWP(php);
}

async function removeMuPlugin(filename) {
  const php = `
    $dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
    $file = $dir . '/' . ${JSON.stringify(filename)};
    if ( file_exists( $file ) ) { @unlink( $file ); }
    echo 'MU_REMOVED';
  `;
  await execWP(php).catch(() => {});
}

const JS_ERROR_SIMULATOR_FILE = 'e2e-js-error-simulator.php';

// Injects broken JS from "other plugins" three different ways to prove the Meta
// pixel still fires in isolation. Uses WooCommerce's shared queue (wc_enqueue_js)
// plus raw footer/jQuery scripts — no dependency on the standalone WooCommerce
// Facebook plugin (which is intentionally not installed).
const JS_ERROR_SIMULATOR_CODE = `<?php
/**
 * Plugin Name: E2E JS Error Simulator
 * Description: Simulates JS errors from other plugins to test isolated pixel event execution.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ERROR 1: Broken JS in the shared WooCommerce inline-JS queue.
add_action( 'wp_footer', function() {
    if ( function_exists( 'wc_enqueue_js' ) ) {
        wc_enqueue_js(
            'console.log("[E2E Test] ERROR 1: Broken JS in wc_queued_js");' .
            'throw new Error("Simulated error in wc_queued_js - tests isolated execution");'
        );
    }
}, 5 );

// ERROR 2: Broken inline JS in footer (common in poorly coded plugins).
add_action( 'wp_footer', function() {
    ?>
    <script>
        console.log("[E2E Test] ERROR 2: Broken inline script in wp_footer");
        throw new Error("Simulated error from another plugin's inline script");
    </script>
    <?php
}, 15 );

// ERROR 3: Broken JS in a jQuery document.ready handler.
add_action( 'wp_footer', function() {
    ?>
    <script>
        jQuery(document).ready(function($) {
            console.log("[E2E Test] ERROR 3: Broken jQuery document.ready handler");
            throw new Error("Simulated error in jQuery document.ready");
        });
    </script>
    <?php
}, 20 );
`;

async function installJsErrorSimulatorMuPlugin() {
  console.log('🔧 Installing JS error simulator mu-plugin...');
  await writeMuPlugin(JS_ERROR_SIMULATOR_FILE, JS_ERROR_SIMULATOR_CODE);
  console.log('✅ JS error simulator mu-plugin installed');
}

async function removeJsErrorSimulatorMuPlugin() {
  console.log('🧹 Removing JS error simulator mu-plugin...');
  await removeMuPlugin(JS_ERROR_SIMULATOR_FILE);
  console.log('✅ JS error simulator mu-plugin removed');
}

const SINGLE_SEARCH_BLOCKER_FILE = 'e2e-single-search-redirect-blocker.php';
const SINGLE_SEARCH_BLOCKER_CODE =
  "<?php\nadd_filter( 'woocommerce_redirect_single_search_result', '__return_false', 999 );\n";

async function installSingleSearchRedirectBlockerMuPlugin() {
  await writeMuPlugin(SINGLE_SEARCH_BLOCKER_FILE, SINGLE_SEARCH_BLOCKER_CODE);
}

async function removeSingleSearchRedirectBlockerMuPlugin() {
  await removeMuPlugin(SINGLE_SEARCH_BLOCKER_FILE);
}

module.exports = {
  writeMuPlugin,
  removeMuPlugin,
  installJsErrorSimulatorMuPlugin,
  removeJsErrorSimulatorMuPlugin,
  installSingleSearchRedirectBlockerMuPlugin,
  removeSingleSearchRedirectBlockerMuPlugin,
};
