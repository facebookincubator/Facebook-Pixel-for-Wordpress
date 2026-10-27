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
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\EventRequest;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\EventResponse;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * Sender whose network call is replaced by a canned response, so the filter
 * and result-shaping logic in the base class runs for real.
 */
final class FakeNetworkSyncCapiSender extends CircuitBreakerAwareSyncCapiSender {
    /**
     * Events the fake "API" saw.
     *
     * @var Event[]
     */
    public $executed_events = array();

    /**
     * Records the request's events and answers as if the API accepted them all.
     *
     * @param EventRequest $request The built request.
     * @return EventResponse
     */
    protected function execute_request( EventRequest $request ) {
        $this->executed_events = $request->getEvents();
        return ( new EventResponse() )->setEventsReceived( count( $this->executed_events ) );
    }
}

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
        $this->assertSame( array(), $result['sent_event_ids'] );
    }

    /**
     * Tests that when the filter removes part of the batch, the result lists
     * only the ids that were actually sent, so a caller acknowledging delivery
     * does not drop the filtered-out events.
     *
     * @return void
     */
    public function testPartialFilteringReportsOnlyTheSentIds() {
        self::mockFacebookWordpressOptions();
        \WP_Mock::userFunction( 'get_transient', array( 'return' => false ) );
        \WP_Mock::userFunction( 'set_transient', array( 'return' => true ) );
        \WP_Mock::userFunction( 'delete_transient', array( 'return' => true ) );

        $api = \Mockery::mock( 'alias:FacebookPixelPlugin\FacebookAds\Api' );
        $api->shouldReceive( 'init' )->andReturn( $api );

        $one   = ( new Event() )->setEventId( 'id-one' );
        $two   = ( new Event() )->setEventId( 'id-two' );
        $three = ( new Event() )->setEventId( 'id-three' );
        $events = array( $one, $two, $three );

        // The filter drops the middle event before the request is built.
        \WP_Mock::onFilter( 'before_conversions_api_event_sent' )
            ->with( $events )
            ->reply( array( $one, $three ) );

        $sender = new FakeNetworkSyncCapiSender( \Mockery::mock( Logger::class ) );

        $result = $sender->send( $events );

        $this->assertTrue( $result['success'] );
        $this->assertSame( 2, $result['events_received'] );
        $this->assertSame( array( 'id-one', 'id-three' ), $result['sent_event_ids'] );
        $this->assertSame( array( $one, $three ), $sender->executed_events );
    }
}
