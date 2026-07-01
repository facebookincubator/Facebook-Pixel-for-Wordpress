<?php
/**
 * Facebook Pixel Plugin SignalsDispatchTest class.
 *
 * Covers the event-dispatch additions to Signals (constructor injection,
 * render, and on() delegating browser delivery).
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
     * The injected server-event store is used and exposed.
     *
     * @return void
     */
    public function testInjectedServerEventStore() {
        $store   = $this->createMock( FacebookServerSideEvent::class );
        $signals = new Signals( $store );

        $this->assertSame( $store, $signals->get_server_events() );
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
