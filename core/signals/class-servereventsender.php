<?php
/**
 * Facebook Pixel Plugin ServerEventSender class.
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
 * Sends server-side events to the Conversions API.
 *
 * Gates sending through the circuit breaker, records success/failure outcomes,
 * logs errors, and resolves the partner agent (including the OpenBridge suffix).
 */
class ServerEventSender extends CapiSenderBase {
    /**
     * Logger for CAPI send failures. Injected so it can be mocked in tests.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Stores the injected logger used to report CAPI send failures.
     *
     * @param Logger $logger Logger for CAPI send failures.
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }

    /**
     * Sends events to the Conversions API. Fire-and-forget: gated by the circuit
     * breaker, records the outcome with it, and returns nothing.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events The events to send.
     * @return void
     */
    public function send( $events ) {
        if ( ! FacebookCapiCircuitBreaker::is_send_allowed() ) {
            return;
        }

        try {
            $this->send_request( $events, $this->get_partner_agent( $events ) );
            FacebookCapiCircuitBreaker::record_success();
        } catch ( \Exception $e ) {
            FacebookCapiCircuitBreaker::record_exception( $e );
            $message = '[Facebook Pixel for WordPress] CAPI error: '
                . $e->getMessage();
            $this->logger->error( $message );
            $this->logger->error( $e->getTraceAsString() );
        }
    }

    /**
     * Checks if the given events are a single OpenBridge event.
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

    /**
     * Suffixes the partner agent with '_ob' for OpenBridge events.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events The events.
     * @return string
     */
    private function get_partner_agent( $events = array() ) {
        $agent = FacebookWordpressOptions::get_agent_string();
        if ( self::is_open_bridge_event( $events ) ) {
            $agent .= '_ob';
        }
        return $agent;
    }
}
