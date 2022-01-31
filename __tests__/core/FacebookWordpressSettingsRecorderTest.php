<?php
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
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressSettingsRecorderTest
  extends FacebookWordpressTestBase {
    public function testAjaxActionsAdded(){
        $settingsRecorder = new FacebookWordpressSettingsRecorder();
        \WP_Mock::expectActionAdded(
            'wp_ajax_save_fbe_settings',
            array($settingsRecorder, 'saveFbeSettings')
        );
        \WP_Mock::expectActionAdded(
          'wp_ajax_delete_fbe_settings',
          array($settingsRecorder, 'deleteFbeSettings')
        );
        $settingsRecorder->init();
    }

    public function mockWordPressFunctions(){
        \WP_Mock::userFunction('current_user_can', array(
          'return' => true,
        ));
        \WP_Mock::userFunction('update_option', array(
          'return' => true,
        ));
        \WP_Mock::userFunction('wp_send_json', array(
          'return' => true,
        ));
        \WP_Mock::userFunction('check_admin_referer', array(
          'return' => true,
        ));
    }

    public function testDoesNotSaveInvalidSettings(){
        $settingsRecorder = new FacebookWordpressSettingsRecorder();
        self::mockWordPressFunctions();
        global $_POST;
        $_POST['pixelId'] = '</script><script>alert(1)</script>';
        $_POST['accessToken'] = '</script><script>alert(2)</script>';
        $_POST['externalBusinessId'] = '</script><script>alert(3)</script>';
        \WP_Mock::userFunction( 'sanitize_text_field', array(
          'return_in_order' => array(
              '',
              '',
              '',
            )
          )
        );
        $expectedJson = array(
          'success' => false,
          'msg' => 'Invalid values'
        );
        $result = $settingsRecorder->saveFbeSettings();
        $this->assertEquals($expectedJson, $result);
    }

    public function testSaveSettingsWithAdmin(){
        $settingsRecorder = new FacebookWordpressSettingsRecorder();
        self::mockWordPressFunctions();
        global $_POST;
        $_POST['pixelId'] = '123';
        $_POST['accessToken'] = 'ABC123XYZ';
        $_POST['externalBusinessId'] = 'fbe_wordpress_123_abc';
        \WP_Mock::userFunction( 'sanitize_text_field', array(
          'return_in_order' => array(
              $_POST['pixelId'],
              $_POST['accessToken'],
              $_POST['externalBusinessId']
            )
          )
        );
        $expectedJson = array(
            'success' => true,
            'msg' => array(
                FacebookPluginConfig::PIXEL_ID_KEY => $_POST['pixelId'],
                FacebookPluginConfig::ACCESS_TOKEN_KEY => $_POST['accessToken'],
                FacebookPluginConfig::EXTERNAL_BUSINESS_ID_KEY =>
                    $_POST['externalBusinessId'],
                FacebookPluginConfig::IS_FBE_INSTALLED_KEY => '1'
            )
        );
        $result = $settingsRecorder->saveFbeSettings();
        $this->assertEquals($expectedJson, $result);
    }
}
