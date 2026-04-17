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
     * Tests that the HPOS compatibility action is registered on
     * before_woocommerce_init.
     *
     * @return void
     */
    public function testHposActionIsRegistered() {
        $this->assertNotFalse(
            has_action( 'before_woocommerce_init' ),
            'before_woocommerce_init action should be registered'
        );
    }

    /**
     * Tests that declare_compatibility is called when FeaturesUtil exists.
     *
     * @return void
     */
    public function testDeclareCompatibilityCalledWhenFeaturesUtilExists() {
        $called_with = null;

        if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            // Create a stub so we can assert the call.
            eval( // phpcs:ignore Squiz.PHP.Eval
                'namespace Automattic\WooCommerce\Utilities;
                class FeaturesUtil {
                    public static $last_call = null;
                    public static function declare_compatibility(
                        $feature_id,
                        $plugin_file,
                        $is_compatible
                    ) {
                        self::$last_call = compact(
                            "feature_id",
                            "plugin_file",
                            "is_compatible"
                        );
                    }
                }'
            );
        }

        do_action( 'before_woocommerce_init' );

        $last_call = \Automattic\WooCommerce\Utilities\FeaturesUtil::$last_call;

        $this->assertNotNull( $last_call );
        $this->assertSame( 'custom_order_tables', $last_call['feature_id'] );
        $this->assertTrue( $last_call['is_compatible'] );
        $this->assertStringEndsWith(
            'facebook-for-wordpress.php',
            $last_call['plugin_file']
        );
    }

    /**
     * Tests that no fatal error occurs when FeaturesUtil does not exist
     * (i.e. WooCommerce is not active).
     *
     * @return void
     */
    public function testNoErrorWhenFeaturesUtilAbsent() {
        // If FeaturesUtil is not loaded, firing the action must not throw.
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            $this->markTestSkipped(
                'FeaturesUtil is already loaded; cannot test the absent-WooCommerce path.'
            );
        }

        $this->expectNotToPerformAssertions();
        do_action( 'before_woocommerce_init' );
    }
}
