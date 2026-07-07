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
 * Class PendingPixelEvents
 *
 * A callback-keyed queue of server events that are held until a specific
 * WordPress callback fires and renders them as pixel code (e.g. WooCommerce's
 * add-to-cart fragment response). Distinct from ServerEventBuffer, which holds
 * the generic events flushed together at a later point in the request.
 * Reached only through Signals.
 */
class PendingPixelEvents {
    /**
     * Map of callback name to the server event to emit when it fires.
     *
     * @var \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[]
     */
    private $events = array();

    /**
     * Stores a server event to be emitted when the named callback fires.
     *
     * @param string                                                   $callback_name The callback name.
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event         The event.
     * @return void
     */
    public function set( $callback_name, $event ) {
        $this->events[ $callback_name ] = $event;
    }

    /**
     * Returns the server event stored for the named callback, or null.
     *
     * @param string $callback_name The callback name.
     * @return \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event|null
     */
    public function get( $callback_name ) {
        if ( isset( $this->events[ $callback_name ] ) ) {
            return $this->events[ $callback_name ];
        }
        return null;
    }
}
