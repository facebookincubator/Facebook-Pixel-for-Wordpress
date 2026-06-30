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
 * Loads the supported form plugins and this plugin before WordPress finishes
 * loading. Each form plugin is loaded only if present, so the suite degrades
 * gracefully when one isn't installed.
 *
 * @return void
 */
function _fb_manually_load_plugins() {
    $form_plugins = array(
        'contact-form-7/wp-contact-form-7.php',
        'wpforms-lite/wpforms.php',
        'ninja-forms/ninja-forms.php',
        'formidable/formidable.php',
    );

    foreach ( $form_plugins as $plugin ) {
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
