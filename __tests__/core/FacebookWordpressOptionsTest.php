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

use FacebookPixelPlugin\Core\AAMSettingsFields;
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in seperate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressOptionsTest extends FacebookWordpressTestBase {
  public function testCanInitialize() {
    self::mockGetOption('1234', '1234');
    self::mockEscJs('1234');
    self::mockGetTransientAAMSettings('1234', true,
      AAMSettingsFields::getAllFields());
    \WP_Mock::expectActionAdded('init', array('FacebookPixelPlugin\\Core\\FacebookWordpressOptions', 'registerUserInfo'), 0);
    FacebookWordpressOptions::initialize();

    $pixel_id = FacebookWordpressOptions::getPixelId();
    $version_info = FacebookWordpressOptions::getVersionInfo();

    $this->assertEquals($pixel_id, '1234');
    $this->assertEquals($version_info['pluginVersion'], FacebookPluginConfig::PLUGIN_VERSION);
    $this->assertEquals($version_info['source'], FacebookPluginConfig::SOURCE);
    $this->assertEquals($version_info['version'], '1.0');
    $this->assertConditionsMet();
    $this->assertHooksAdded();
  }

  public function testCanRegisterUserInfo() {
    self::mockWpGetCurrentUser('1234');

    self::mockGetOption('1234', '1234', '1');
    self::mockEscJs('1234');
    self::mockGetTransientAAMSettings('1234', true,
      AAMSettingsFields::getAllFields());
    \WP_Mock::expectActionAdded('init', array('FacebookPixelPlugin\\Core\\FacebookWordpressOptions', 'registerUserInfo'), 0);
    FacebookWordpressOptions::initialize();

    FacebookWordpressOptions::registerUserInfo();
    $user_info = FacebookWordpressOptions::getUserInfo();
    $this->assertEquals($user_info['em'], 'foo@foo.com');
    $this->assertEquals($user_info['fn'], 'john');
    $this->assertEquals($user_info['ln'], 'doe');
  }

  public function testCannotRegisterUserInfoWithoutUserId() {
    self::mockWpGetCurrentUser();

    self::mockGetOption('1234', '1234');
    self::mockEscJs('1234');
    self::mockGetTransientAAMSettings('1234', true,
      AAMSettingsFields::getAllFields());
    \WP_Mock::expectActionAdded('init', array('FacebookPixelPlugin\\Core\\FacebookWordpressOptions', 'registerUserInfo'), 0);
    FacebookWordpressOptions::initialize();

    FacebookWordpressOptions::registerUserInfo();
    $user_info = FacebookWordpressOptions::getUserInfo();
    $this->assertEquals(\count($user_info), 0);
  }

  public function testCannotRegisterUserInfoWithoutUsePII() {
    self::mockWpGetCurrentUser('1234');

    self::mockGetOption('1234', '1234');
    self::mockEscJs();
    self::mockGetTransientAAMSettings('1234', false,
      AAMSettingsFields::getAllFields());
    \WP_Mock::expectActionAdded('init', array('FacebookPixelPlugin\\Core\\FacebookWordpressOptions', 'registerUserInfo'), 0);
    FacebookWordpressOptions::initialize();

    FacebookWordpressOptions::registerUserInfo();
    $user_info = FacebookWordpressOptions::getUserInfo();

    $this->assertEquals(\count($user_info), 0);
  }

  public function testCanSetVersionInfoAndGetAgentString() {
    $GLOBALS['wp_version'] = '1.1';
    FacebookWordpressOptions::setVersionInfo();

    $version_info = FacebookWordpressOptions::getVersionInfo();
    $this->assertEquals($version_info['pluginVersion'], FacebookPluginConfig::PLUGIN_VERSION);
    $this->assertEquals($version_info['source'], FacebookPluginConfig::SOURCE);
    $this->assertEquals($version_info['version'], '1.1');

    $agent_string = FacebookWordpressOptions::getAgentString();
    $this->assertEquals($agent_string,
      FacebookPluginConfig::SOURCE . '-1.1-' . FacebookPluginConfig::PLUGIN_VERSION);
  }

  public function testDefaultValuesAreCorrect() {
    self::mockEscJs('');
    self::mockGetOption();
    \WP_Mock::expectActionAdded('init', array('FacebookPixelPlugin\\Core\\FacebookWordpressOptions', 'registerUserInfo'), 0);
    FacebookWordpressOptions::initialize();

    $pixel_id = FacebookWordpressOptions::getPixelId();
    $version_info = FacebookWordpressOptions::getVersionInfo();
    $access_token = FacebookWordpressOptions::getAccessToken();
    $is_fbe_installed = FacebookWordpressOptions::getIsFbeInstalled();
    $external_business_id = FacebookWordpressOptions::getExternalBusinessId();

    $this->assertEquals($pixel_id, '');
    $this->assertEquals($access_token, '');
    $this->assertEquals($is_fbe_installed, '0');
    $this->assertContains('fbe_wordpress_', $external_business_id);
  }

  private function mockGetOption($mock_pixel_id=null, $mock_access_token=null,
    $mock_fbe_installed = '0') {
    \WP_Mock::userFunction('get_option', array(
      'return' =>
        array(
          FacebookPluginConfig::PIXEL_ID_KEY =>
            is_null($mock_pixel_id) ? FacebookWordpressOptions::getDefaultPixelID() : $mock_pixel_id,
          FacebookPluginConfig::ACCESS_TOKEN_KEY =>
            is_null($mock_access_token) ?
            FacebookWordpressOptions::getDefaultAccessToken() :
            $mock_access_token,
          FacebookPluginConfig::IS_FBE_INSTALLED_KEY =>
            $mock_fbe_installed
        ),
    ));
  }

  private function mockGetTransientAAMSettings(
    $pixel_id = null,
    $enable_aam = false,
    $aam_fields = []
  ){
    define( 'MINUTE_IN_SECONDS', 60 );
    \WP_Mock::userFunction('get_transient', array(
      'return' => [
          "pixelId" => $pixel_id,
          "enableAutomaticMatching" => $enable_aam,
          "enabledAutomaticMatchingFields" => $aam_fields,
        ]
      ));
  }

  private function mockEscJs($string = '1234') {
    \WP_Mock::userFunction('esc_js', array(
      'args' => $string,
      'return' => $string,
    ));
  }

  private function mockWpGetCurrentUser($user_id = 0) {
    \WP_Mock::userFunction('wp_get_current_user', array(
      'return' => (object) [
        'ID' => $user_id,
        'user_email' => 'foo@foo.com',
        'user_firstname' => 'John',
        'user_lastname' => 'Doe',
      ],
    ));
  }
}
