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
 * Class AdminEventSender
 *
 * Sends admin "Test Events" to the Conversions API and returns the result so
 * the admin panel can report it (events_received on success, the error message
 * otherwise). Unlike ServerEventSender it never consults the circuit breaker,
 * so a rejected test event cannot block real traffic.
 *
 * @internal Not part of the plugin's public API.
 */
class AdminEventSender extends CapiSenderBase {
    /**
     * Sends test events to the Conversions API and returns the result.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events          The events to send.
     * @param string|null                                                $test_event_code Optional test event code.
     * @return array Result array with a 'success' key.
     */
    public function send( array $events, $test_event_code = null ) {
        if ( ! $this->has_config() ) {
            return array(
                'success' => false,
                'error'   => array(
                    'message' => 'Pixel ID or access token is not configured.',
                    'code'    => 0,
                ),
            );
        }

        try {
            $response = $this->send_request(
                $events,
                FacebookWordpressOptions::get_agent_string(),
                $test_event_code
            );
            return array(
                'success'         => true,
                'events_received' => null === $response ? 0 : $response->getEventsReceived(),
            );
        } catch ( \Exception $e ) {
            return array(
                'success' => false,
                'error'   => array(
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
                ),
            );
        }
    }
}
