<?php
/**
 * Bootstrap for the WordPress integration test suite.
 *
 * Boots the WordPress test library and loads the supported form plugins (plus
 * this plugin) so the discovery API can be exercised against real plugin code.
 *
 * @package FacebookPixelPlugin
 */

/*
* Copyright (C) 2017-present, Meta, Inc.
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; version 2 of the License.
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*/

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$_functions = $_tests_dir . '/includes/functions.php';
if ( ! file_exists( $_functions ) ) {
    echo "Could not find the WordPress test suite at {$_tests_dir}." . PHP_EOL;
    echo 'Run bin/install-wp-tests.sh first (see README).' . PHP_EOL;
    exit( 1 );
}

require_once $_functions;

// Ensure this plugin's Composer autoloader is available for its classes.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Main plugin files (relative to the WP plugins dir) for the form plugins we
 * support and test discovery against.
 *
 * @return string[]
 */
function _fb_form_plugin_files() {
    return array(
        'contact-form-7/wp-contact-form-7.php',
        'wpforms-lite/wpforms.php',
        'ninja-forms/ninja-forms.php',
        'formidable/formidable.php',
    );
}

/**
 * Loads the supported form plugins and this plugin before WordPress finishes
 * loading. Each form plugin is loaded only if present, so the suite degrades
 * gracefully when one isn't installed.
 *
 * @return void
 */
function _fb_manually_load_plugins() {
    foreach ( _fb_form_plugin_files() as $plugin ) {
        $path = WP_PLUGIN_DIR . '/' . $plugin;
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }

    require_once dirname( __DIR__ ) . '/facebook-for-wordpress.php';
}
tests_add_filter( 'muplugins_loaded', '_fb_manually_load_plugins' );

// The WordPress test suite requires the Yoast PHPUnit Polyfills; point it at
// the Composer-installed copy.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
    define(
        'WP_TESTS_PHPUNIT_POLYFILLS_PATH',
        dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills'
    );
}

require_once $_tests_dir . '/includes/bootstrap.php';

// Activate the form plugins so those that use custom database tables (Ninja
// Forms, Formidable) run their install routines and create them. Loading the
// plugin file alone does not fire activation. Each activation is isolated so a
// heavy/failing one doesn't abort the whole suite.
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
foreach ( _fb_form_plugin_files() as $fb_plugin ) {
    if ( ! file_exists( WP_PLUGIN_DIR . '/' . $fb_plugin ) ) {
        continue;
    }
    try {
        ob_start();
        activate_plugin( $fb_plugin );
        ob_end_clean();
    } catch ( \Throwable $fb_e ) {
        if ( ob_get_level() > 0 ) {
            ob_end_clean();
        }
        // Best-effort: tests assert availability/shape regardless, and
        // form-creation assertions degrade to skips when persistence is
        // unavailable.
        error_log( 'Integration activate_plugin failed for ' . $fb_plugin . ': ' . $fb_e->getMessage() );
    }
}
