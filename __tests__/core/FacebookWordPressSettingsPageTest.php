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

use FacebookPixelPlugin\Core\FacebookWordpressSettingsPage;
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordPressSettingsPageTest extends FacebookWordpressTestBase {
    public function testNotificationWithMissingPixel() {
        $this->mockFacebookWordpressOptions(
            array(
                'pixel_id' => null,
                'access_token' => null,
            )
        );
        $settings_page =
            new FacebookWordpressSettingsPage('facebook_for_wordpress');
        $message = $settings_page->getCustomizedFbeNotInstalledNotice();
        $expected_prefix = sprintf(
            '<strong>%s</strong> is almost ready.',
            FacebookPluginConfig::PLUGIN_NAME
        );
        $this->assertStringStartsWith($expected_prefix, $message);
    }

    public function testNotificationWithValidPixelAndMissingAccessToken(){
        $this->mockFacebookWordpressOptions(
            array(
                'pixel_id' => '1234',
                'access_token' => null,
            )
        );
        $settings_page =
            new FacebookWordpressSettingsPage('facebook_for_wordpress');
        $message = $settings_page->getCustomizedFbeNotInstalledNotice();
        $expected_prefix = sprintf(
            '<strong>%s</strong> gives you access to the Conversions API.',
            FacebookPluginConfig::PLUGIN_NAME
        );
        $this->assertStringStartsWith($expected_prefix, $message);
    }

    public function testNotificationWithValidPixelAndValidAccessToken(){
        $this->mockFacebookWordpressOptions(
            array(
                'pixel_id' => '1234',
                'access_token' => 'abc',
            )
        );
        $settings_page =
            new FacebookWordpressSettingsPage('facebook_for_wordpress');
        $message = $settings_page->getCustomizedFbeNotInstalledNotice();
        $expected_prefix = sprintf(
            'Easily manage your connection to Meta with <strong>%s</strong>.',
            FacebookPluginConfig::PLUGIN_NAME
        );
        $this->assertStringStartsWith($expected_prefix, $message);
    }

}
