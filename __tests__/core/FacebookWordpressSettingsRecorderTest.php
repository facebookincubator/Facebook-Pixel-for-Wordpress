<?php
/**
 * Facebook Pixel Plugin FacebookWordpressSettingsRecorderTest class.
 *
 * This file contains the main logic for FacebookWordpressSettingsRecorderTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressSettingsRecorderTest class.
 *
 * @return void
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

use FacebookPixelPlugin\Core\FacebookWordpressSettingsRecorder;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\FacebookPluginConfig;

/**
 * FacebookWordpressSettingsRecorderTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressSettingsRecorderTest extends FacebookWordpressTestBase {
    /**
     * Verifies that the Ajax actions are added to WordPress.
     *
     * We're verifying that the save_fbe_settings
     * and delete_fbe_settings methods are
     * properly added as Ajax actions in WordPress.
     *
     * @covers FacebookWordpressSettingsRecorder::init
     */
    public function testAjaxActionsAdded() {
        $settings_recorder = new FacebookWordpressSettingsRecorder();
    \WP_Mock::expectActionAdded(
        'wp_ajax_save_fbe_settings',
        array( $settings_recorder, 'save_fbe_settings' )
    );
    \WP_Mock::expectActionAdded(
        'wp_ajax_delete_fbe_settings',
        array( $settings_recorder, 'delete_fbe_settings' )
    );
        $settings_recorder->init();
    }

    /**
     * Mocks WordPress functions for tests.
     *
     * This function sets up stubs for WordPress functions that are used in the
     * FacebookWordpressSettingsRecorder class. The stubs are set up to return
     * true for all invocations.
     *
     * @return void
     */
    public function mockWordPressFunctions() {
    \WP_Mock::userFunction(
        'current_user_can',
        array(
            'return' => true,
        )
    );
    \WP_Mock::userFunction(
        'update_option',
        array(
            'return' => true,
        )
    );
    \WP_Mock::userFunction(
        'wp_send_json',
        array(
            'return' => true,
        )
    );
    \WP_Mock::userFunction(
        'check_admin_referer',
        array(
            'return' => true,
        )
    );
    }

    /**
     * Tests that invalid settings are not saved.
     *
     * This test verifies that the FacebookWordpressSettingsRecorder
     * class does not save
     * settings when the pixel ID, access token, or external business
     * ID contain invalid
     * values. The test case sets up invalid values for the
     * $_POST superglobal and
     * verifies that the save_fbe_settings method returns an error.
     *
     * @covers FacebookWordpressSettingsRecorder::save_fbe_settings
     */
    public function testDoesNotSaveInvalidSettings() {
        $settings_recorder = new FacebookWordpressSettingsRecorder();
        self::mockWordPressFunctions();
        global $_POST;
        $_POST['pixelId']            = '</script><script>alert(1)</script>';
        $_POST['accessToken']        = '</script><script>alert(2)</script>';
        $_POST['externalBusinessId'] = '</script><script>alert(3)</script>';

    \WP_Mock::userFunction(
        'wp_unslash',
        array(
            'args'   => array( \Mockery::any() ),
            'return' => function ( $input ) {
                return $input;
            },
        )
    );

    \WP_Mock::userFunction(
        'sanitize_text_field',
        array(
            'return_in_order' => array(
                '',
                '',
                '',
            ),
        )
    );

        $expected_json = array(
            'success' => false,
            'msg'     => 'Invalid values',
        );
        $result        = $settings_recorder->save_fbe_settings();
        $this->assertEquals( $expected_json, $result );
    }

    /**
     * Tests that settings are saved when the current user is an administrator.
     *
     * This test verifies that the FacebookWordpressSettingsRecorder class saves
     * settings when the current user is an administrator.
     * It sets up valid values
     * for the $_POST superglobal and calls the save_fbe_settings method.
     * The test
     * verifies that the settings are saved correctly by comparing the result
     * with the expected output.
     *
     * @covers FacebookWordpressSettingsRecorder::save_fbe_settings
     */
    public function testSaveSettingsWithAdmin() {
        $settings_recorder = new FacebookWordpressSettingsRecorder();
        self::mockWordPressFunctions();
        global $_POST;
        $_POST['pixelId']            = '123';
        $_POST['accessToken']        = 'ABC123XYZ';
        $_POST['externalBusinessId'] = 'fbe_wordpress_123_abc';
    if ( isset( $_POST['pixelId'] ) && isset(
        $_POST['accessToken']
    ) && isset( $_POST['externalBusinessId'] ) ) {
        \WP_Mock::userFunction(
            'sanitize_text_field',
            array(
                'return_in_order' => array(
                    $_POST['pixelId'],
                    $_POST['accessToken'],
                    $_POST['externalBusinessId'],
                ),
            )
        );
        $expected_json = array(
            'success' => true,
            'msg'     => array(
                FacebookPluginConfig::PIXEL_ID_KEY         => $_POST['pixelId'],
                FacebookPluginConfig::ACCESS_TOKEN_KEY     => $_POST['accessToken'],
                FacebookPluginConfig::EXTERNAL_BUSINESS_ID_KEY => $_POST['externalBusinessId'],
                FacebookPluginConfig::IS_FBE_INSTALLED_KEY => '1',
            ),
        );

        \WP_Mock::userFunction(
            'wp_unslash',
            array(
                'args'   => array( \Mockery::any() ),
                'return' => function ( $input ) {
                    return $input;
                },
            )
        );

        $result = $settings_recorder->save_fbe_settings();
        $this->assertEquals( $expected_json, $result );
    }
    }
}
