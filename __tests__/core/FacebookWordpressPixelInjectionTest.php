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

  public function testPixelInjectionWithoutServerSideApi() {
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

    FacebookWordpressOptions::initialize();
    $injectionObj->inject();

    foreach ($spies as $index => $spy) {
      $spy->shouldHaveReceived('injectPixelCode');
    }
  }

  public function testPixelInjectionWithServerSideApi() {
    self::mockGetOption(1234, true);
    $injectionObj = new FacebookWordpressPixelInjection();
    \WP_Mock::expectActionAdded(
      'wp_head', array($injectionObj, 'injectPixelCode'));
    \WP_Mock::expectActionAdded(
      'wp_head', array($injectionObj, 'injectPixelNoscriptCode'));
    \WP_Mock::expectActionAdded(
      'shutdown', array($injectionObj, 'sendServerEvents'));

    $spies = array();
    foreach (self::$integrations as $index => $integration) {
      $spies[] = \Mockery::spy(
        'alias:FacebookPixelPlugin\\Integration\\' . $integration);
    }

    FacebookWordpressOptions::initialize();
    $injectionObj->inject();

    foreach ($spies as $index => $spy) {
      $spy->shouldHaveReceived('injectPixelCode');
    }
  }

  private function mockGetOption(
    $mock_pixel_id = '',
    $mock_use_s2s = false,
    $mock_access_token = ''
   ) {
    \WP_Mock::userFunction('get_option', array(
      'return' =>
        array(
          FacebookPluginConfig::PIXEL_ID_KEY => $mock_pixel_id,
          FacebookPluginConfig::ACCESS_TOKEN_KEY => $mock_access_token,
          FacebookPluginConfig::USE_S2S_KEY => $mock_use_s2s,
        ),
    ));
  }
}
