<?php
/**
 * @package FacebookPixelPlugin
 */

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

use FacebookPixelPlugin\Core\Capi;
use FacebookPixelPlugin\Core\CapiSenderBase;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * CapiTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class CapiTest extends FacebookWordpressTestBase {
  /**
   * Test that a synchronous send hands the transport's result back to the
   * caller, so a release can tell delivered events from failed ones.
   *
   * @return void
   */
  public function testSyncSendReturnsTheSenderResult() {
    $events = array( ( new Event() )->setEventId( 'id-one' ) );
    $result = array( 'success' => false, 'error' => array( 'message' => 'Connection invalid (circuit open)', 'code' => 0 ) );

    $sync = \Mockery::mock( CapiSenderBase::class );
    $sync->shouldReceive( 'send' )->once()->with( $events )->andReturn( $result );
    $async = \Mockery::mock( CapiSenderBase::class );
    $async->shouldReceive( 'send' )->never();

    $capi = new Capi( $sync, $async );

    $this->assertSame( $result, $capi->send( $events, Capi::CAPI_SYNC ) );
  }

  /**
   * Test that an unknown mode is rejected before anything is sent.
   *
   * @return void
   */
  public function testUnknownModeIsRejected() {
    \WP_Mock::userFunction(
      'esc_html',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );

    $sync = \Mockery::mock( CapiSenderBase::class );
    $sync->shouldReceive( 'send' )->never();
    $async = \Mockery::mock( CapiSenderBase::class );
    $async->shouldReceive( 'send' )->never();

    $this->expectException( \Exception::class );
    ( new Capi( $sync, $async ) )->send( array(), 'nope' );
  }
}
