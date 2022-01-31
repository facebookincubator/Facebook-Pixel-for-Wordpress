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
use FacebookPixelPlugin\Core\FacebookWordpressPixelInjection;
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressPixelInjectionTest
  extends FacebookWordpressTestBase {

  private static $integrations = array(
    'FacebookWordpressCalderaForm',
    'FacebookWordpressContactForm7',
    'FacebookWordpressEasyDigitalDownloads',
    'FacebookWordpressFormidableForm',
    'FacebookWordpressGravityForms',
    'FacebookWordpressMailchimpForWp',
    'FacebookWordpressNinjaForms',
    'FacebookWordpressWPForms',
    'FacebookWordpressWPECommerce',
  );

  public function testPixelInjection() {
    self::mockGetOption(1234);
    $injectionObj = new FacebookWordpressPixelInjection();
    \WP_Mock::expectActionAdded(
      'wp_head', array($injectionObj, 'injectPixelCode'));
    \WP_Mock::expectActionAdded(
      'wp_head', array($injectionObj, 'injectPixelNoscriptCode'));

    $spies = array();
    foreach (self::$integrations as $index => $integration) {
      $spies[] = \Mockery::spy(
        'alias:FacebookPixelPlugin\\Integration\\' . $integration);
    }

    \WP_Mock::expectActionNotAdded(
      'shutdown', array($injectionObj, 'sendServerEvents'));

    self::mockGetTransientAAMSettings(1234, false,
      AAMSettingsFields::getAllFields());

    FacebookWordpressOptions::initialize();
    $injectionObj->inject();

    foreach ($spies as $index => $spy) {
      $spy->shouldHaveReceived('injectPixelCode');
    }
  }

  public function testServerEventSendingInjection(){
    self::mockGetOption(1234, 'abc');
    self::mockGetTransientAAMSettings('1234', false,
      AAMSettingsFields::getAllFields());
    $injectionObj = new FacebookWordpressPixelInjection();
    \WP_Mock::expectActionAdded(
      'wp_footer', array($injectionObj, 'sendPendingEvents'));
    FacebookWordpressOptions::initialize();
    $injectionObj->inject();
  }

  private function mockGetOption(
    $mock_pixel_id = '',
    $mock_access_token = ''
   ) {
    \WP_Mock::userFunction('get_option', array(
      'return' =>
        array(
          FacebookPluginConfig::PIXEL_ID_KEY => $mock_pixel_id,
          FacebookPluginConfig::ACCESS_TOKEN_KEY => $mock_access_token,
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
}
