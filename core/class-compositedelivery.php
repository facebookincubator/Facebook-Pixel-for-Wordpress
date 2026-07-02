<?php
/**
 * Facebook Pixel Plugin CompositeDelivery class.
 *
 * Fans a single event registration out to several delivery strategies (e.g. an
 * integration that must deliver both in the footer and in an AJAX response,
 * where only one path actually runs per request). Each queued event is
 * forwarded to every wrapped delivery.
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
 * Class CompositeDelivery
 */
class CompositeDelivery implements EventDelivery {
    /**
     * Wrapped delivery strategies.
     *
     * @var EventDelivery[]
     */
    private $deliveries;

    /**
     * Constructor.
     *
     * @param EventDelivery[] $deliveries The delivery strategies to fan out to.
     */
    public function __construct( array $deliveries ) {
        $this->deliveries = $deliveries;
    }

    /**
     * Registers every wrapped delivery.
     *
     * @param string $tracking_name The integration tracking name.
     * @return void
     */
    public function register( $tracking_name ) {
        foreach ( $this->deliveries as $delivery ) {
            if ( $delivery instanceof EventDelivery ) {
                $delivery->register( $tracking_name );
            }
        }
    }

    /**
     * Queues the event onto every wrapped delivery.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @return void
     */
    public function queue( $event ) {
        foreach ( $this->deliveries as $delivery ) {
            if ( $delivery instanceof EventDelivery ) {
                $delivery->queue( $event );
            }
        }
    }
}
