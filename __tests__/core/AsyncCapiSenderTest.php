<?php
/**
 * Facebook Pixel Plugin AsyncCapiSenderTest class.
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

use FacebookPixelPlugin\Core\AsyncCapiSender;
use FacebookPixelPlugin\Core\ServerEventAsyncTask;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * AsyncCapiSenderTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AsyncCapiSenderTest extends FacebookWordpressTestBase {

    /**
     * Tests that events from multiple send() calls are buffered and dispatched
     * in a single background postback (a lone flush) rather than each call
     * overwriting the previous — the WP_Async_Task single-payload bug.
     *
     * @return void
     */
    public function testBuffersMultipleSendsIntoSinglePostback() {
        $sender  = new AsyncCapiSender();
        $event_a = new \stdClass();
        $event_b = new \stdClass();

        // The shutdown flush must be registered exactly once across both sends.
        \WP_Mock::expectActionAdded( 'shutdown', array( $sender, 'flush' ), 0 );

        $sender->send( array( $event_a ) );
        $sender->send( array( $event_b ) );

        // A single dispatch carries BOTH events (count 2 -> array payload).
        \WP_Mock::expectAction(
            ServerEventAsyncTask::ACTION,
            array( $event_a, $event_b ),
            2
        );

        $sender->flush();

        $this->assertHooksAdded();
        $this->assertConditionsMet();
    }

    /**
     * Tests that a single buffered event is dispatched as a bare Event object
     * (num_events === 1), matching ServerEventAsyncTask::prepare_data()'s
     * contract.
     *
     * @return void
     */
    public function testSingleEventDispatchedAsObject() {
        $sender = new AsyncCapiSender();
        $event  = new \stdClass();

        \WP_Mock::expectActionAdded( 'shutdown', array( $sender, 'flush' ), 0 );

        $sender->send( array( $event ) );

        \WP_Mock::expectAction( ServerEventAsyncTask::ACTION, $event, 1 );

        $sender->flush();

        $this->assertHooksAdded();
        $this->assertConditionsMet();
    }

    /**
     * Tests that flushing with nothing buffered dispatches nothing.
     *
     * @return void
     */
    public function testFlushWithoutEventsIsNoop() {
        $sender = new AsyncCapiSender();

        $sender->send( array() );
        $sender->flush();

        $this->assertConditionsMet();
    }
}
