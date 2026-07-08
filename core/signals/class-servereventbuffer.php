<?php
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
 *
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Core;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class ServerEventBuffer
 *
 * The request-scoped store of the generic server-side (CAPI) events that are
 * accumulated and flushed together at a later point in the request. It only
 * holds events; it does not decide consent gating or send anything — Signals
 * owns that and ServerEventSender does the sending. The separate callback-keyed
 * pixel events live in KeyedEventBuffer. Reached only through Signals.
 *
 * @internal Not part of the plugin's public API; use the Signals facade.
 */
class ServerEventBuffer {
    /**
     * All events tracked during the request.
     *
     * @var \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[]
     */
    private $tracked_events = array();

    /**
     * Events queued to be sent to the Conversions API.
     *
     * @var \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[]
     */
    private $pending_events = array();

    /**
     * Records an event as tracked during this request.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @return void
     */
    public function record( $event ) {
        $this->tracked_events[] = $event;
    }

    /**
     * Queues an event to be sent to the Conversions API.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @return void
     */
    public function queue_for_send( $event ) {
        $this->pending_events[] = $event;
    }

    /**
     * Retrieves the events tracked during the current request.
     *
     * When an event type is provided, only the tracked events whose event name
     * matches are returned (re-indexed); otherwise all tracked events are
     * returned in insertion order.
     *
     * @param string|null $event_type Optional event name to filter by (e.g. 'Lead').
     * @return array An array of tracked events.
     */
    public function get_tracked_events( $event_type = null ) {
        if ( null === $event_type ) {
            return $this->tracked_events;
        }

        return array_values(
            array_filter(
                $this->tracked_events,
                function ( $event ) use ( $event_type ) {
                    return $event->getEventName() === $event_type;
                }
            )
        );
    }

    /**
     * Retrieves the number of events tracked during the current request.
     *
     * @return int The number of tracked events.
     */
    public function get_num_tracked_events() {
        return count( $this->tracked_events );
    }

    /**
     * Retrieves all the events queued to be sent.
     *
     * @return array An array of events queued to be sent.
     */
    public function get_pending_events() {
        return $this->pending_events;
    }
}
