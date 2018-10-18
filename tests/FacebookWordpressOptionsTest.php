<?php
namespace FacebookPixelPlugin\Tests;

use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Core\FacebookPluginConfig;

final class FacebookWordpressOptionsTest extends FacebookWordpressTestBase {

  public function testCanInitialize() {
    self::$mockPixelId = 1234;
    self::$mockUsePII = '0';

    FacebookWordpressOptions::initialize();

    $pixel_id = FacebookWordpressOptions::getPixelId();
    $use_pii = FacebookWordpressOptions::getUsePii();
    $version_info = FacebookWordpressOptions::getVersionInfo();

    $this->assertEquals($pixel_id, '1234');
    $this->assertEquals($use_pii, '0');
    $this->assertEquals(self::$addActionCallCount, 1);
    $this->assertEquals($version_info['pluginVersion'], FacebookPluginConfig::PLUGIN_VERSION);
    $this->assertEquals($version_info['source'], FacebookPluginConfig::SOURCE);
    $this->assertEquals($version_info['version'], '1.0');
  }

  public function testCanRegisterUserInfo() {
    self::$mockUsePII = '1';
    self::$mockUserId = 1234;

    FacebookWordpressOptions::initialize();
    FacebookWordpressOptions::registerUserInfo();
    $user_info = FacebookWordpressOptions::getUserInfo();

    $this->assertEquals($user_info['em'], 'foo@foo.com');
    $this->assertEquals($user_info['fn'], 'John');
    $this->assertEquals($user_info['ln'], 'Doe');
  }

  public function testCannotRegisterUserInfoWithoutUserId() {
    self::$mockUsePII = '1';
    FacebookWordpressOptions::initialize();
    FacebookWordpressOptions::registerUserInfo();
    $user_info = FacebookWordpressOptions::getUserInfo();

    $this->assertEquals(\count($user_info), 0);
  }

  public function testCannotRegisterUserInfoWithoutUsePII() {
    self::$mockUserId = 1234;
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
      FacebookPluginConfig::SOURCE.'-1.1-'.FacebookPluginConfig::PLUGIN_VERSION);
  }
}
