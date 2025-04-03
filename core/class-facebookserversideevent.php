<?php
/**
 * Facebook Pixel Plugin FacebookServerSideEvent class.
 *
 * This file contains the main logic for FacebookServerSideEvent.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookServerSideEvent class.
 *
 * @return void
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

use FacebookAds\Api;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\EventRequest;
use FacebookAds\Object\ServerSide\UserData;
use FacebookAds\Exception\Exception;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class FacebookServerSideEvent
 */
class FacebookServerSideEvent {
    /**
     * The instance of the FacebookServerSideEvent class.
     *
     * @var FacebookServerSideEvent
     */
    private static $instance = null;

    /**
     * Contains all the events triggered during the request.
     *
     * @var FacebookServerSideEvent
     */
    private $tracked_events = array();

    /**
     * Contains all Conversions API events that have not been sent.
     *
     * @var FacebookServerSideEvent
     */
    private $pending_events = array();

    /**
     * Maps a callback name with a Conversions API event
     * that hasn't been rendered as pixel event.
     *
     * @var FacebookServerSideEvent
     */
    private $pending_pixel_events = array();

    /**
     * Retrieves the instance of FacebookServerSideEvent class.
     *
     * @return FacebookServerSideEvent The instance of
     * FacebookServerSideEvent class.
     */
    public static function get_instance() {
    if ( null === self::$instance ) {
        self::$instance = new FacebookServerSideEvent();
    }
        return self::$instance;
    }

    /**
     * Tracks a given event and optionally sends it immediately.
     *
     * @param object $event   The event to be tracked.
     * @param bool   $send_now Optional. Whether to send the event immediately.
     *                        Defaults to true. If true, the event will be sent
     *                        immediately. If false, the event will be added to
     *                        the pending events queue.
     */
    public function track( $event, $send_now = true ) {
        $this->tracked_events[] = $event;
        if ( $send_now ) {
            do_action(
                'send_server_events',
                array( $event ),
                1
            );
        } else {
            $this->pending_events[] = $event;
        }
    }

    /**
     * Retrieves all the events tracked during the current request.
     *
     * @return array An array of tracked events.
     */
    public function get_tracked_events() {
        return $this->tracked_events;
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
     * Retrieves all the events that have not been sent yet.
     *
     * @return array An array of events that have not been sent yet.
     */
    public function get_pending_events() {
        return $this->pending_events;
    }

    /**
     * Stores a server event that should be sent when a specific
     * callback is fired.
     *
     * @param string      $callback_name The name of the callback
     * to listen for.
     * @param ServerEvent $event The server event to send when the
     *  callback is fired.
     */
    public function set_pending_pixel_event( $callback_name, $event ) {
        $this->pending_pixel_events[ $callback_name ] = $event;
    }

    /**
     * Retrieves a server event that should be sent when a specific
     * callback is fired.
     *
     * @param string $callback_name The name of the callback to listen for.
     * @return ServerEvent|null The server event to send when the callback
     * is fired, or null if no event was stored for the callback.
     */
    public function get_pending_pixel_event( $callback_name ) {
    if ( isset( $this->pending_pixel_events[ $callback_name ] ) ) {
        return $this->pending_pixel_events[ $callback_name ];
    }
        return null;
    }

    /**
     * Sends a list of events to the Conversions API.
     *
     * This function can be used to send events to the Conversions API directly.
     * It will apply the 'before_conversions_api_event_sent'
     * filter to the events before sending them.
     *
     * @param ServerEvent[] $events The events to send to the Conversions API.
     *
     * @throws \Exception If there was an error sending the events to
     * the Conversions API.
     */
    public static function send( $events ) {
        $events = apply_filters( 'before_conversions_api_event_sent', $events );
        if ( empty( $events ) ) {
            return;
        }

        $pixel_id     = FacebookWordpressOptions::get_pixel_id();
        $access_token = FacebookWordpressOptions::get_access_token();
        $agent        = FacebookWordpressOptions::get_agent_string();

        if ( self::is_open_bridge_event( $events ) ) {
            $agent .= '_ob'; // agent suffix is openbridge.
        }

        if ( empty( $pixel_id ) || empty( $access_token ) ) {
            return;
        }
        try {
            $api = Api::init( null, null, $access_token );

            $request = ( new EventRequest( $pixel_id ) )
                    ->setEvents( $events )
                    ->setPartnerAgent( $agent );

            $response = $request->execute();
        } catch ( \Exception $e ) {
            throw $e;
        }
    }

    /**
     * Checks if the given event is an OpenBridge event.
     *
     * This function determines if the provided event array contains exactly one
     * event and if that event has custom data with a 'fb_integration_tracking'
     * property set to 'wp-cloudbridge-plugin'. If these conditions are met,
     * the function returns true, indicating the event is an OpenBridge event.
     *
     * @param array $events An array of events to check.
     * @return bool True if the event is an OpenBridge event, false otherwise.
     */
    private static function is_open_bridge_event( $events ) {
        if ( count( $events ) !== 1 ) {
            return false;
        }

        $custom_data = $events[0]->getCustomData();
        if ( ! $custom_data ) {
            return false;
        }

        $custom_properties = $custom_data->getCustomProperties();
        if ( ! $custom_properties ||
            ! isset( $custom_properties['fb_integration_tracking'] ) ) {
            return false;
        }

        return 'wp-cloudbridge-plugin' ===
        $custom_properties['fb_integration_tracking'];
    }
}
