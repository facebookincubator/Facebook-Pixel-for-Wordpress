<?php
/**
 * Facebook Pixel Plugin Capi class.
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
 * Handles sending events through the Conversions API (CAPI).
 *
 * Wraps a ServerEventSender and exposes synchronous and deferred (action-hooked)
 * ways to dispatch server-side events.
 */
class Capi {

    /**
     * Is the actual class that handles sending the Rest Call
     *
     * @var \FacebookPixelPlugin\Core\ServerEventSender $sender
     */
    private $sender;

    /**
     * Initializes the CAPI with a ServerEventSender backed by a Logger.
     */
    public function __construct() {
        $this->sender = new ServerEventSender( new Logger() );
    }

    /**
     * Sends the given events to the Conversions API synchronously.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events The events to send.
     * @return void
     */
    public function send( $events ) {

        $this->sender->send( $events );
    }

    /**
     * Defers sending the given events until the specified WordPress action fires.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events   The events to send.
     * @param string                                                     $hook     The WordPress action hook to send on.
     * @param int                                                        $priority The action priority. Default 10.
     * @return void
     */
    public function send_async( $events, $hook, $priority = 10 ) {
        add_action(
            $hook,
            function () use ( $events ) {
                $this->send( $events );
            },
            $priority
        );
    }
}
