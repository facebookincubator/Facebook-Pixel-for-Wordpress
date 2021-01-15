<?php
/*
 * Copyright (C) 2017-present, Facebook, Inc.
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

    public function testSaveSettingsWithAdmin(){
        $settingsRecorder = new FacebookWordpressSettingsRecorder();
        \WP_Mock::userFunction('current_user_can', array(
            'return' => true,
          ));
          \WP_Mock::userFunction('update_option', array(
            'return' => true,
          ));
          \WP_Mock::userFunction('wp_send_json', array(
            'return' => true,
          ));
        global $_POST;
        $_POST['pixelId'] = '123';
        $_POST['accessToken'] = 'abc';
        $_POST['externalBusinessId'] = 'fbe_wordpress_1';
        $expectedJson = array(
            'success' => true,
            'msg' => array(
                FacebookPluginConfig::PIXEL_ID_KEY => '123',
                FacebookPluginConfig::ACCESS_TOKEN_KEY => 'abc',
                FacebookPluginConfig::EXTERNAL_BUSINESS_ID_KEY =>
                    'fbe_wordpress_1',
                FacebookPluginConfig::IS_FBE_INSTALLED_KEY => '1'
            )
        );
        $result = $settingsRecorder->saveFbeSettings();
        $this->assertEquals($expectedJson, $result);
    }
}
