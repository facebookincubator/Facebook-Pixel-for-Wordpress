<?php
/**
 * Facebook Pixel Plugin CircuitBreakerAwareSyncCapiSenderTest class.
 *
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

use FacebookPixelPlugin\Core\CircuitBreakerAwareSyncCapiSender;
use FacebookPixelPlugin\Core\Logger;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * CircuitBreakerAwareSyncCapiSenderTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class CircuitBreakerAwareSyncCapiSenderTest extends FacebookWordpressTestBase {

    /**
     * Tests that when the before_conversions_api_event_sent filter removes every
     * event, send() returns a benign result (no request made) instead of
     * dereferencing a null response — the null-response regression.
     *
     * @return void
     */
    public function testFilterEmptyingEventsReturnsBenignResult() {
        self::mockFacebookWordpressOptions();
        // Circuit closed: is_send_allowed() reads this transient and allows send.
        \WP_Mock::userFunction( 'get_transient', array( 'return' => false ) );

        $event = \Mockery::mock(
            'FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event'
        );
        $event->shouldReceive( 'getCustomData' )->andReturn( null );
        $events = array( $event );

        // The filter drops all events before the request is built.
        \WP_Mock::onFilter( 'before_conversions_api_event_sent' )
            ->with( $events )
            ->reply( array() );

        $sender = new CircuitBreakerAwareSyncCapiSender(
            \Mockery::mock( Logger::class )
        );

        $result = $sender->send( $events );

        $this->assertTrue( $result['success'] );
        $this->assertSame( 0, $result['events_received'] );
    }
}
