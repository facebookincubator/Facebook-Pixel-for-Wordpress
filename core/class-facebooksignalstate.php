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

use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Static per-request state for held signal delivery.
 */
class FacebookSignalState {
    /**
     * Whether signals are currently held for this request.
     *
     * @var bool
     */
    private static $held = false;

    /**
     * CAPI events queued while signals are held, keyed by event_id.
     *
     * @var array<string, Event>
     */
    private static $queued_events = array();

    /**
     * Attribution data captured while signals are held (e.g. fbp, fbc).
     *
     * @var array<string, string>
     */
    private static $attribution_data = array();

    /**
     * Hold signals for the current request.
     *
     * @return void
     */
    public static function hold() {
        self::$held = true;
    }

    /**
     * Release signals for the current request.
     *
     * @return void
     */
    public static function release() {
        self::$held = false;
    }

    /**
     * Whether signals are currently held.
     *
     * @return bool
     */
    public static function is_held() {
        return (bool) apply_filters(
            'facebook_signals_held',
            self::$held
        );
    }

    /**
     * Queue a CAPI event so it can be enriched on release.
     *
     * @param Event $event Event to queue.
     * @return void
     */
    public static function queue_event( Event $event ) {
        $event_id = $event->getEventId();
        if ( empty( $event_id ) ) {
            return;
        }
        self::$queued_events[ $event_id ] = $event;
    }

    /**
     * Get a queued CAPI event by event_id.
     *
     * @param string $event_id Event identifier.
     * @return Event|null
     */
    public static function get_queued_event( $event_id ) {
        return isset( self::$queued_events[ $event_id ] )
            ? self::$queued_events[ $event_id ]
            : null;
    }

    /**
     * Get the safe-to-forward normalized user_data for a queued event.
     *
     * Strips fields that the release request will derive from its own context
     * (client_ip_address, client_user_agent, fbp, fbc) so the release can build
     * those from cookies/headers it actually sees.
     *
     * @param string $event_id Event identifier.
     * @return array|null
     */
    public static function get_queued_user_data( $event_id ) {
        $event = self::get_queued_event( $event_id );
        if ( null === $event ) {
            return null;
        }

        $user_data = $event->getUserData();
        if ( null === $user_data ) {
            return null;
        }

        $normalized = $user_data->normalize();
        unset(
            $normalized['client_ip_address'],
            $normalized['client_user_agent'],
            $normalized['fbp'],
            $normalized['fbc']
        );

        return ! empty( $normalized ) ? $normalized : null;
    }

    /**
     * Store attribution data captured while signals are held.
     *
     * @param string $key   Data key (e.g. 'fbp', 'fbc').
     * @param string $value Data value.
     * @return void
     */
    public static function set_attribution_data( $key, $value ) {
        self::$attribution_data[ $key ] = $value;
    }

    /**
     * Retrieve stored attribution data.
     *
     * @param string $key Data key.
     * @return string|null
     */
    public static function get_attribution_data( $key ) {
        return isset( self::$attribution_data[ $key ] )
            ? self::$attribution_data[ $key ]
            : null;
    }

    /**
     * Reset the queued events and attribution stores. Intended for tests.
     *
     * @return void
     */
    public static function reset_queue() {
        self::$queued_events    = array();
        self::$attribution_data = array();
    }
}
