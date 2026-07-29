<?php
/**
 * Facebook Pixel Plugin CircuitBreakerAwareSyncCapiSender class.
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

use FacebookPixelPlugin\FacebookAds\Http\Exception\RequestException;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Sends server-side events to the Conversions API synchronously (inline, within
 * the current request).
 *
 * Gates sending through the circuit breaker, records success/failure outcomes,
 * logs errors, and resolves the partner agent (including the OpenBridge suffix).
 * See AsyncCapiSender for the out-of-band (background) path.
 */
class CircuitBreakerAwareSyncCapiSender extends CapiSenderBase {
    /**
     * Logger for CAPI send failures. Injected so it can be mocked in tests.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Optional test event code; when set, the send is flagged as a test event
     * (and its RequestExceptions are treated as expected, not logged).
     *
     * @var string
     */
    private $test_event_code;

    /**
     * Stores the test event code and the injected logger used to report CAPI
     * send failures.
     *
     * @param Logger $logger          Logger for CAPI send failures.
     * @param string $test_event_code Optional test event code.
     */
    public function __construct( Logger $logger, $test_event_code = '' ) {
        $this->logger          = $logger;
        $this->test_event_code = $test_event_code;
    }

    /**
     * Sends events to the Conversions API inline. Gated by the circuit breaker,
     * records the outcome with it, and returns a result array in the shape
     * FacebookCapiEvent consumes ('success', 'events_received' or 'error').
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events The events to send.
     * @return array The send result: 'success' bool, plus 'events_received' on
     *               success or an 'error' payload on failure.
     */
    public function send( $events ) {
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
            $response = $this->send_request( $events, $this->get_partner_agent( $events ), $this->test_event_code );
            if ( null === $response ) {
                // The before_conversions_api_event_sent filter removed every
                // event, so no request was made and there is no outcome to
                // record on the circuit breaker.
                return array(
                    'success'         => true,
                    'events_received' => 0,
                );
            }
            FacebookCapiCircuitBreaker::record_success();
            return array(
                'success'         => true,
                'events_received' => $response->getEventsReceived(),
            );
        } catch ( \Exception $e ) {
            FacebookCapiCircuitBreaker::record_exception( $e );
            if ( $this->should_log_exception( $e ) ) {
                $this->logger->error(
                    '[Facebook Pixel for WordPress] CAPI error: ' . $e->getMessage()
                );
                $this->logger->error( $e->getTraceAsString() );
            }
            return array(
                'success' => false,
                'error'   => $this->build_error_payload( $e ),
            );
        }
    }

    /**
     * Builds a user-facing error payload from an exception, preferring the CAPI
     * "error_user_msg" when the SDK provides one.
     *
     * @param \Exception $e The exception raised by the CAPI send.
     * @return array The error payload: 'message', 'error_user_msg' and 'code'.
     */
    private function build_error_payload( \Exception $e ) {
        $error_message      = $e->getMessage();
        $error_user_message = $error_message;

        if ( $e instanceof RequestException ) {
            $error_user_message = $e->getErrorUserMessage();
            if ( empty( $error_user_message ) ) {
                $error_user_message = $error_message;
            }
        }

        return array(
            'message'        => $error_message,
            'error_user_msg' => $error_user_message,
            'code'           => $e->getCode(),
        );
    }

    /**
     * Whether an exception should be logged. Test-event RequestExceptions are
     * expected when a payload is deliberately invalid, so they are not logged.
     *
     * @param \Exception $e The exception raised by the CAPI send.
     * @return bool
     */
    private function should_log_exception( \Exception $e ) {
        if ( ! empty( $this->test_event_code ) && $e instanceof RequestException ) {
            return false;
        }
        return true;
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
