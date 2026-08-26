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

use FacebookPixelPlugin\FacebookAds\Http\Exception\RequestException;

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
            $message = 'Pixel ID or access token is not configured.';
            return array(
                'success' => false,
                'error'   => array(
                    'message'        => $message,
                    'error_user_msg' => $message,
                    'code'           => 0,
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
                'error'   => $this->build_error_payload( $e ),
            );
        }
    }

    /**
     * Builds a user-facing error payload from a failed send.
     *
     * Conversions API rejections arrive as RequestException, which carries a
     * user-friendly message distinct from the generic exception message;
     * surface that to the admin panel, falling back to the generic message.
     *
     * @param \Exception $e The exception raised by the send.
     * @return array
     */
    private function build_error_payload( \Exception $e ) {
        $message      = $e->getMessage();
        $user_message = $message;

        if ( $e instanceof RequestException ) {
            $user_message = $e->getErrorUserMessage();
            if ( empty( $user_message ) ) {
                $user_message = $message;
            }
        }

        return array(
            'message'        => $message,
            'error_user_msg' => $user_message,
            'code'           => $e->getCode(),
        );
    }
}
