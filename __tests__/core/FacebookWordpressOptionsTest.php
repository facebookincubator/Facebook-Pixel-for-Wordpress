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
    self::mockGetOption('1234', '0', false, '1234');
    self::mockEscJs('1234');
    \WP_Mock::expectActionAdded('init', array('FacebookPixelPlugin\\Core\\FacebookWordpressOptions', 'registerUserInfo'), 0);
    FacebookWordpressOptions::initialize();

    $pixel_id = FacebookWordpressOptions::getPixelId();
    $use_pii = FacebookWordpressOptions::getUsePii();
    $version_info = FacebookWordpressOptions::getVersionInfo();

    $this->assertEquals($pixel_id, '1234');
    $this->assertEquals($use_pii, '0');
    $this->assertEquals($version_info['pluginVersion'], FacebookPluginConfig::PLUGIN_VERSION);
    $this->assertEquals($version_info['source'], FacebookPluginConfig::SOURCE);
    $this->assertEquals($version_info['version'], '1.0');
    $this->assertConditionsMet();
    $this->assertHooksAdded();
  }

  public function testCanRegisterUserInfo() {
    self::mockWpGetCurrentUser('1234');

    self::mockGetOption('1234', '1', false, '1234');
    self::mockEscJs('1234');
    \WP_Mock::expectActionAdded('init', array('FacebookPixelPlugin\\Core\\FacebookWordpressOptions', 'registerUserInfo'), 0);
    FacebookWordpressOptions::initialize();

    FacebookWordpressOptions::registerUserInfo();
    $user_info = FacebookWordpressOptions::getUserInfo();
    $this->assertEquals($user_info['em'], 'foo@foo.com');
    $this->assertEquals($user_info['fn'], 'John');
    $this->assertEquals($user_info['ln'], 'Doe');
  }

  public function testCannotRegisterUserInfoWithoutUserId() {
    self::mockWpGetCurrentUser();

    self::mockGetOption('1234', '1', false, '1234');
    self::mockEscJs('1234');
    \WP_Mock::expectActionAdded('init', array('FacebookPixelPlugin\\Core\\FacebookWordpressOptions', 'registerUserInfo'), 0);
    FacebookWordpressOptions::initialize();

    FacebookWordpressOptions::registerUserInfo();
    $user_info = FacebookWordpressOptions::getUserInfo();
    $this->assertEquals(\count($user_info), 0);
  }

  public function testCannotRegisterUserInfoWithoutUsePII() {
    self::mockWpGetCurrentUser('1234');

    self::mockGetOption('1234', '0', false, '1234');
    self::mockEscJs();
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
    $use_pii = FacebookWordpressOptions::getUsePii();
    $version_info = FacebookWordpressOptions::getVersionInfo();

    $this->assertEquals($pixel_id, '');
    $this->assertEquals($use_pii, '1');
  }

  private function mockGetOption($mock_pixel_id=null, $mock_use_pii=null, $mock_use_s2s=null, $mock_access_token=null) {
    \WP_Mock::userFunction('get_option', array(
      'return' =>
        array(
          FacebookPluginConfig::PIXEL_ID_KEY =>
            is_null($mock_pixel_id) ? FacebookWordpressOptions::getDefaultPixelID() : $mock_pixel_id,
          FacebookPluginConfig::USE_PII_KEY =>
            is_null($mock_use_pii) ? FacebookWordpressOptions::getDefaultUsePIIKey() : $mock_use_pii,
          FacebookPluginConfig::USE_S2S_KEY =>
            $mock_use_s2s ? FacebookWordpressOptions::getDefaultUseS2SKey() : $mock_use_s2s,
          FacebookPluginConfig::ACCESS_TOKEN_KEY =>
            is_null($mock_access_token) ? FacebookWordpressOptions::getDefaultAccessToken() : $mock_access_token,
        ),
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
