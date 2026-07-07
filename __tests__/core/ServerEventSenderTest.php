<?php
/**
 * Facebook Pixel Plugin ServerEventSenderTest class.
 *
 * @package FacebookPixelPlugin
 */

/**
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

use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Core\ServerEventSender;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * ServerEventSenderTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class ServerEventSenderTest extends FacebookWordpressTestBase {
  /**
   * A fresh sender for each test.
   *
   * @var ServerEventSender
   */
  private $sender;

  public function setUp(): void {
    parent::setUp();
    $this->sender = new ServerEventSender();
  }

  public function testSendReturnsNullWhenNoCredentials() {
    self::mockFacebookWordpressOptions(
      array(
        'pixel_id'     => '',
        'access_token' => '',
      )
    );

    $api = \Mockery::mock( 'alias:FacebookPixelPlugin\FacebookAds\Api' );
    $api->shouldReceive( 'init' )->never();

    $event  = ( new Event() )->setEventName( 'Lead' );
    $result = $this->sender->send( array( $event ) );

    $this->assertNull( $result );
  }

  public function testSendReturnsErrorWhenCircuitOpen() {
    self::mockFacebookWordpressOptions();

    \WP_Mock::userFunction(
      'get_transient',
      array(
        'args'   => array(
          FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT,
        ),
        'return' => time() - 60,
      )
    );

    $api = \Mockery::mock( 'alias:FacebookPixelPlugin\FacebookAds\Api' );
    $api->shouldReceive( 'init' )->never();

    $event  = ( new Event() )->setEventName( 'Lead' );
    $result = $this->sender->send( array( $event ) );

    $this->assertFalse( $result['success'] );
    $this->assertStringContainsString(
      'circuit',
      $result['error']['message']
    );
  }

  public function testSendBlocksOnlyWhenTransientFresh() {
    self::mockFacebookWordpressOptions();

    // Fresh transient → circuit open → should block.
    \WP_Mock::userFunction(
      'get_transient',
      array(
        'args'   => array(
          FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT,
        ),
        'return' => time() - 10,
      )
    );

    $event  = ( new Event() )->setEventName( 'Lead' );
    $result = $this->sender->send( array( $event ) );

    $this->assertFalse( $result['success'] );
    $this->assertStringContainsString(
      'circuit',
      $result['error']['message']
    );
  }
}
