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

use FacebookPixelPlugin\FacebookAds\Api;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\EventRequest;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class ServerEventSender
 *
 * Sends server-side (CAPI) events to the Conversions API and owns the API
 * client instance. It is a plain sender: consent gating and the
 * before_conversions_api_event_sent filter live in Signals; this class assumes
 * it is only handed events that should actually be sent. Reached only through
 * Signals.
 */
class ServerEventSender {
    /**
     * Lazily-initialized Conversions API client, reused across sends in the
     * same request (the access token is request-stable).
     *
     * @var \FacebookPixelPlugin\FacebookAds\Api|null
     */
    private $api = null;

    /**
     * Sends a list of events to the Conversions API.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events          The events to send.
     * @param string|null                                                $test_event_code Optional test event code.
     * @return array|null Result array with a 'success' key, or null if skipped.
     */
    public function send( $events, $test_event_code = null ) {
        $pixel_id     = FacebookWordpressOptions::get_active_pixel_id();
        $access_token = FacebookWordpressOptions::get_active_access_token();
        $agent        = FacebookWordpressOptions::get_agent_string();

        if ( self::is_open_bridge_event( $events ) ) {
            $agent .= '_ob';
        }

        if ( empty( $pixel_id ) || empty( $access_token ) ) {
            return null;
        }

        if ( ! FacebookCapiCircuitBreaker::is_send_allowed() ) {
            return array(
                'success' => false,
                'error'   => array(
                    'message' => 'Connection invalid (circuit open)',
                    'code'    => 0,
                ),
            );
        }

        try {
            if ( null === $this->api ) {
                $this->api = Api::init( null, null, $access_token );
            }

            $request = ( new EventRequest( $pixel_id ) )
                    ->setEvents( $events )
                    ->setPartnerAgent( $agent );

            if ( $test_event_code ) {
                $request->setTestEventCode( $test_event_code );
            }

            $response = $request->execute();

            FacebookCapiCircuitBreaker::record_success();

            return array(
                'success'         => true,
                'events_received' => $response->getEventsReceived(),
            );
        } catch ( \Exception $e ) {
            FacebookCapiCircuitBreaker::record_exception( $e );
            // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[Facebook Pixel for WordPress] CAPI error: ' . $e->getMessage() );
            error_log( $e->getTraceAsString() );
            // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return array(
                'success' => false,
                'error'   => array(
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
                ),
            );
        }
    }

    /**
     * Checks if the given event is an OpenBridge event.
     *
     * Determines whether the array contains exactly one event whose custom data
     * has a 'fb_integration_tracking' property set to 'wp-cloudbridge-plugin',
     * in which case the partner agent is suffixed with '_ob'.
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
