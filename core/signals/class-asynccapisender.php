<?php
/**
 * Facebook Pixel Plugin AsyncCapiSender class.
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

namespace FacebookPixelPlugin\Core;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Sends server-side events to the Conversions API asynchronously (out-of-band).
 *
 * Rather than making the CAPI request inline, this hands the events to the
 * ServerEventAsyncTask background task (a non-blocking WP_Async_Task postback),
 * which performs the actual send in a separate request so the visitor's page is
 * not blocked on the network round-trip. See CircuitBreakerAwareSyncCapiSender
 * for the inline path.
 *
 * Events are BUFFERED and dispatched once per request. WP_Async_Task keeps only
 * the payload of the last do_action() before it fires its single shutdown
 * postback, so firing per send() call would drop every batch but the last.
 * Buffering and flushing once on shutdown guarantees every event dispatched
 * during the request rides the same background postback.
 *
 * The circuit breaker and pixel/token configuration are intentionally NOT
 * consulted here: matching the original async flow, those checks live in the
 * background send (ServerEventAsyncTask::run_action() -> FacebookServerSideEvent
 * ::send()), which is where the request actually goes out.
 */
class AsyncCapiSender extends CapiSenderBase {
    /**
     * Events accumulated during the request, flushed once on shutdown.
     *
     * @var \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[]
     */
    private $buffered_events = array();

    /**
     * Whether the shutdown flush has already been registered this request.
     *
     * @var bool
     */
    private $flush_registered = false;

    /**
     * Buffers events for background delivery and ensures a single flush is
     * scheduled for the end of the request.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events The events to send.
     * @return void
     */
    public function send( $events ) {
        if ( empty( $events ) ) {
            return;
        }

        foreach ( $events as $event ) {
            $this->buffered_events[] = $event;
        }

        if ( ! $this->flush_registered ) {
            // Flush early in shutdown so the do_action() below runs before
            // WP_Async_Task's own shutdown postback (default priority 10).
            add_action( 'shutdown', array( $this, 'flush' ), 0 );
            $this->flush_registered = true;
        }
    }

    /**
     * Dispatches all buffered events to the background task in a single
     * postback. Shaped to ServerEventAsyncTask::prepare_data()'s contract: a lone
     * event is passed as a single object (num_events === 1), otherwise the whole
     * array is passed.
     *
     * @return void
     */
    public function flush() {
        if ( empty( $this->buffered_events ) ) {
            return;
        }

        $events                = array_values( $this->buffered_events );
        $this->buffered_events = array();
        $count                 = count( $events );

        if ( 1 === $count ) {
            do_action( ServerEventAsyncTask::ACTION, $events[0], 1 );
        } else {
            do_action( ServerEventAsyncTask::ACTION, $events, $count );
        }
    }
}
