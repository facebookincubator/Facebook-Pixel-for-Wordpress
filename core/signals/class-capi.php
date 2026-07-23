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
 * Owns two senders and exposes both an inline (synchronous) and an out-of-band
 * (asynchronous, background) way to dispatch server-side events.
 */
class Capi {

    const CAPI_SYNC  = 'sync';
    const CAPI_ASYNC = 'async';

    /**
     * Sends the CAPI request inline, within the current request.
     *
     * @var \FacebookPixelPlugin\Core\CapiSenderBase $sync_sender
     */
    private $sync_sender;

    /**
     * Dispatches the CAPI request to a background task (out-of-band).
     *
     * @var \FacebookPixelPlugin\Core\CapiSenderBase $async_sender
     */
    private $async_sender;

    /**
     * Initializes the CAPI with the sync and async senders.
     *
     * @param \FacebookPixelPlugin\Core\CapiSenderBase $sync_sender  Inline sender.
     * @param \FacebookPixelPlugin\Core\CapiSenderBase $async_sender Background sender.
     */
    public function __construct( $sync_sender, $async_sender ) {
        $this->sync_sender  = $sync_sender;
        $this->async_sender = $async_sender;
    }

    /**
     * Sends the given events to the Conversions API using the requested mode:
     * CAPI_SYNC delivers inline within the current request; CAPI_ASYNC hands the
     * events to a non-blocking background task so the current request is not
     * blocked on the network round-trip.
     *
     * When signals are held on a front-end request, the events are queued for
     * later release instead of being sent (see should_suppress_frontend_send()).
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events The events to send.
     * @param string                                                     $mode   CAPI_SYNC or CAPI_ASYNC.
     * @throws \Exception When $mode is not a supported value.
     * @return void
     */
    public function send( $events, $mode ) {

        switch ( $mode ) {
            case self::CAPI_SYNC:
                $this->sync_sender->send( $events );
                break;
            case self::CAPI_ASYNC:
                $this->async_sender->send( $events );
                break;
            default:
                throw new \Exception( esc_html( $mode ) . ' is not a supported value for $mode argument' );
        }
    }
}
