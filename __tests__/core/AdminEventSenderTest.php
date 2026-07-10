<?php
/**
 * Facebook Pixel Plugin AdminEventSenderTest class.
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

use FacebookPixelPlugin\Core\AdminEventSender;
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * AdminEventSenderTest class.
 *
 * AdminEventSender sends admin "Test Events" and returns the result for the
 * admin panel. Unlike ServerEventSender it never consults the circuit breaker,
 * so a rejected test event cannot block real traffic.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class AdminEventSenderTest extends FacebookWordpressTestBase {
  /**
   * A fresh sender for each test.
   *
   * @var AdminEventSender
   */
  private $sender;

  public function setUp(): void {
    parent::setUp();
    $this->sender = new AdminEventSender();
  }

  public function testSendReturnsErrorWhenNoCredentials() {
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

    $this->assertFalse( $result['success'] );
    $this->assertStringContainsString(
      'configured',
      $result['error']['message']
    );
  }

  public function testSendBypassesCircuitBreaker() {
    self::mockFacebookWordpressOptions();

    // Fresh transient → circuit would be open for ServerEventSender. The admin
    // sender must ignore it and still attempt the call (init is reached).
    \WP_Mock::userFunction(
      'get_transient',
      array(
        'args'   => array(
          FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT,
        ),
        'return' => time(),
      )
    );

    $api = \Mockery::mock( 'alias:FacebookPixelPlugin\FacebookAds\Api' );
    $api->shouldReceive( 'init' )
      ->once()
      ->andThrow( new \Exception( 'boom' ) );

    $event  = ( new Event() )->setEventName( 'Lead' );
    $result = $this->sender->send( array( $event ) );

    // Reached the API (breaker bypassed) and surfaced the error to the caller.
    $this->assertFalse( $result['success'] );
    $this->assertEquals( 'boom', $result['error']['message'] );
    $this->assertConditionsMet();
  }
}
