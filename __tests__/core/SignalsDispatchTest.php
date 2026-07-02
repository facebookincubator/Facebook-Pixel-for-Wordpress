<?php
/**
 * Facebook Pixel Plugin SignalsDispatchTest class.
 *
 * Covers the event-dispatch additions to Signals (constructor injection,
 * pending-event flushing, and on() delegating browser delivery).
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

use FacebookPixelPlugin\Core\Signals;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\EventDelivery;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * SignalsDispatchTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SignalsDispatchTest extends FacebookWordpressTestBase {

    /**
     * Sets up WP function stubs for hook registration.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        \WP_Mock::userFunction( 'add_action', array( 'return' => true ) );
        \WP_Mock::userFunction( 'add_filter', array( 'return' => true ) );
    }

    /**
     * flush_pending_events sends the queued events from the injected store via
     * the send_server_events action.
     *
     * @return void
     */
    public function testFlushPendingEventsSendsQueuedEvents() {
        $event = new \stdClass();
        $store = $this->createMock( FacebookServerSideEvent::class );
        $store->method( 'get_pending_events' )->willReturn( array( $event ) );
        $signals = new Signals( $store );

        \WP_Mock::expectAction( 'send_server_events', array( $event ), 1 );

        $signals->flush_pending_events();

        $this->assertConditionsMet();
    }

    /**
     * flush_pending_events does nothing when the store has no queued events.
     *
     * @return void
     */
    public function testFlushPendingEventsNoopWhenEmpty() {
        $store = $this->createMock( FacebookServerSideEvent::class );
        $store->method( 'get_pending_events' )->willReturn( array() );
        $signals = new Signals( $store );

        $signals->flush_pending_events();

        $this->assertConditionsMet();
    }

    /**
     * on() registers the chosen browser delivery strategy with the tracking
     * name.
     *
     * @return void
     */
    public function testOnRegistersDelivery() {
        $store    = $this->createMock( FacebookServerSideEvent::class );
        $signals  = new Signals( $store );
        $delivery = $this->createMock( EventDelivery::class );

        $delivery->expects( $this->once() )
            ->method( 'register' )
            ->with( 'contact-form-7' );

        $signals->on(
            'wpcf7_submit',
            'Lead',
            function () {
                return null;
            },
            $delivery,
            'contact-form-7'
        );
    }
}
