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
 * Class KeyedEventBuffer
 *
 * A key-addressed buffer of server events: each event is stored under a string
 * key and retrieved by that key. Used to hold an event until a specific
 * WordPress callback fires and renders it as pixel code (the key being that
 * callback's name, e.g. WooCommerce's add-to-cart fragment response). Distinct
 * from ServerEventBuffer, which holds the generic events flushed together at a
 * later point in the request. Reached only through Signals.
 *
 * @internal Not part of the plugin's public API; use the Signals facade.
 */
class KeyedEventBuffer {
    /**
     * Map of key to the server event stored under it.
     *
     * @var \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[]
     */
    private $events = array();

    /**
     * Stores a server event under the given key.
     *
     * @param string                                                   $key   The key.
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @return void
     */
    public function set( $key, $event ) {
        $this->events[ $key ] = $event;
    }

    /**
     * Returns the server event stored under the given key, or null.
     *
     * @param string $key The key.
     * @return \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event|null
     */
    public function get( $key ) {
        if ( isset( $this->events[ $key ] ) ) {
            return $this->events[ $key ];
        }
        return null;
    }
}
