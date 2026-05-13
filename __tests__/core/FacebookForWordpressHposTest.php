<?php
/**
 * Facebook Pixel Plugin FacebookForWordpressHposTest class.
 *
 * This file contains tests for the HPOS compatibility declaration.
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

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * Tests for the HPOS (High-Performance Order Storage) compatibility
 * declaration in facebook-for-wordpress.php.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookForWordpressHposTest extends FacebookWordpressTestBase {

    /**
     * Load the plugin entrypoint with minimal mocks needed for this test.
     *
     * @return void
     */
    private function bootstrapPluginEntrypoint() {
        if ( ! defined( 'ABSPATH' ) ) {
            define( 'ABSPATH', __DIR__ );
        }

        if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
            define( 'WEEK_IN_SECONDS', 604800 );
        }

        \WP_Mock::userFunction(
            'plugin_dir_path',
            array( 'return' => dirname( __DIR__, 2 ) . '/' )
        );
        \WP_Mock::userFunction(
            'plugin_basename',
            array( 'return' => 'facebook-pixel-for-wordpress/facebook-for-wordpress.php' )
        );
        \WP_Mock::userFunction( 'load_plugin_textdomain', array( 'return' => true ) );
        \WP_Mock::userFunction( 'get_transient', array( 'return' => false ) );
        \WP_Mock::userFunction( 'update_option', array( 'return' => true ) );
        \WP_Mock::userFunction( 'set_transient', array( 'return' => true ) );
        \WP_Mock::userFunction( 'is_admin', array( 'return' => false ) );

        $mocked_options = \Mockery::mock( 'alias:FacebookPixelPlugin\\Core\\FacebookWordpressOptions' );
        $mocked_options->shouldReceive( 'initialize' )->once();
        $mocked_options->shouldReceive( 'get_options' )->andReturn( array() );
        $mocked_options->shouldReceive( 'get_pixel_id' )->andReturn( '1234' );
        $mocked_options->shouldReceive( 'get_active_pixel_id' )->andReturn( '1234' );
        $mocked_options->shouldReceive( 'is_wordpress_com_hosted' )->andReturn( false );

        $mocked_pixel = \Mockery::mock( 'alias:FacebookPixelPlugin\\Core\\FacebookPixel' );
        $mocked_pixel->shouldReceive( 'initialize' )->once();

        \Mockery::mock( 'overload:FacebookPixelPlugin\\Core\\ServerEventAsyncTask' );

        require_once dirname( __DIR__, 2 ) . '/facebook-for-wordpress.php';
    }

    /**
     * Tests that the HPOS compatibility action is registered on
     * before_woocommerce_init.
     *
     * @return void
     */
    public function testHposActionIsRegistered() {
        \WP_Mock::expectActionAdded(
            'before_woocommerce_init',
            array( '\\FacebookPixelPlugin\\FacebookForWordpress', 'declare_hpos_compatibility' )
        );

        $this->bootstrapPluginEntrypoint();
    }

    /**
     * Tests that declare_compatibility is called when FeaturesUtil exists.
     *
     * @return void
     */
    public function testDeclareCompatibilityCalledWhenFeaturesUtilExists() {
        $mocked_features_util = \Mockery::mock(
            'alias:Automattic\\WooCommerce\\Utilities\\FeaturesUtil'
        );
        $mocked_features_util->shouldReceive( 'declare_compatibility' )
            ->once()
            ->with(
                'custom_order_tables',
                'facebook-pixel-for-wordpress/facebook-for-wordpress.php',
                true
            );

        $this->bootstrapPluginEntrypoint();
        \FacebookPixelPlugin\FacebookForWordpress::declare_hpos_compatibility();
    }

    /**
     * Tests that no fatal error occurs when FeaturesUtil does not exist
     * (i.e. WooCommerce is not active).
     *
     * @return void
     */
    public function testNoErrorWhenFeaturesUtilAbsent() {
        $this->bootstrapPluginEntrypoint();
        \FacebookPixelPlugin\FacebookForWordpress::declare_hpos_compatibility();
        $this->assertTrue( true );
    }
}
