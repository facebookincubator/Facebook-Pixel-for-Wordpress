<?php
/**
 * Facebook Pixel Plugin EventDelivery interface.
 *
 * A delivery strategy decides WHERE the browser pixel code is emitted (page
 * footer, an AJAX response key, appended HTML, ...). Events dispatched for a
 * given on() registration are queued onto its delivery, and the delivery
 * renders exactly those — it does not read a shared tracked-events list.
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
 * Interface EventDelivery
 */
interface EventDelivery {
    /**
     * Registers the WordPress hook(s) that emit this delivery's queued events
     * and records the integration tracking name used when rendering.
     *
     * @param string $tracking_name The integration tracking name.
     * @return void
     */
    public function register( $tracking_name );

    /**
     * Queues a dispatched event to be rendered by this delivery.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @return void
     */
    public function queue( $event );
}
