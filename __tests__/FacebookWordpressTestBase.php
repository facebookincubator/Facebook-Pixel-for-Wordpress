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

namespace FacebookPixelPlugin\Tests;

use \WP_Mock\Tools\TestCase;
use FacebookPixelPlugin\Core\AAMSettingsFields;
use FacebookAds\Object\ServerSide\AdsPixelSettings;

abstract class FacebookWordpressTestBase extends TestCase {
  public function setUp() {
    \WP_Mock::setUp();
    $GLOBALS['wp_version'] = '1.0';
    \Mockery::getConfiguration()->setConstantsMap([
      'FacebookPixelPlugin\Core\FacebookPixel' => [
        'FB_INTEGRATION_TRACKING_KEY' => 'fb_integration_tracking',
      ],
    ]);

    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_HOST'] = 'www.pikachu.com';
    $_SERVER['REQUEST_URI'] = '/index.php';
  }

  public function tearDown() {
    $this->addToAssertionCount(
      \Mockery::getContainer()->mockery_getExpectationCount());
    unset($GLOBALS['wp_version']);
    \WP_Mock::tearDown();
  }

  protected function mockIsInternalUser($is_internal_user) {
    $this->mocked_fbpixel = \Mockery::mock
      ('alias:FacebookPixelPlugin\Core\FacebookPluginUtils');
    $this->mocked_fbpixel->shouldReceive('isInternalUser')
      ->andReturn($is_internal_user);
  }

  protected function mockFacebookWordpressOptions($options = array(),
    $aam_settings = null){
    $this->mocked_options = \Mockery::mock(
      'alias:FacebookPixelPlugin\Core\FacebookWordpressOptions');
    if(array_key_exists('agent_string', $options)){
      $this->mocked_options->shouldReceive('getAgentString')->andReturn($options['agent_string']);
    }
    else{
      $this->mocked_options ->shouldReceive('getAgentString')
                            ->andReturn('wordpress');
    }
    if(array_key_exists('pixel_id', $options)){
      $this->mocked_options->shouldReceive('getPixelId')->andReturn($options['pixel_id']);
    }
    else{
      $this->mocked_options->shouldReceive('getPixelId')->andReturn('1234');
    }
    if(array_key_exists('access_token', $options)){
      $this->mocked_options->shouldReceive('getAccessToken')->andReturn($options['access_token']);
    }
    else{
      $this->mocked_options->shouldReceive('getAccessToken')->andReturn('abcd');
    }
    if(array_key_exists('is_fbe_installed', $options)){
      $this->mocked_options->shouldReceive('getIsFbeInstalled')->andReturn($options['is_fbe_installed']);
    }
    else{
      $this->mocked_options->shouldReceive('getIsFbeInstalled')->andReturn('0');
    }
    if($aam_settings == null){
      $this->mocked_options->shouldReceive('getAAMSettings')->andReturn($this->getDefaultAAMSettings());
    }
    else{
      $this->mocked_options->shouldReceive('getAAMSettings')->andReturn($aam_settings);
    }
  }

  protected function getDefaultAAMSettings(){
    $aam_settings = new AdsPixelSettings();
    $aam_settings->setPixelId('123');
    $aam_settings->setEnableAutomaticMatching(true);
    $aam_settings->setEnabledAutomaticMatchingFields(AAMSettingsFields::getAllFields());
    return $aam_settings;
  }
}
