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

use FacebookPixelPlugin\FacebookAds\Api;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\EventRequest;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\UserData;
use FacebookPixelPlugin\FacebookAds\Exception\Exception;
use FacebookPixelPlugin\FacebookAds\Http\Exception\RequestException;
use FacebookPixelPlugin\FacebookAds\Http\Exception\AuthorizationException;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class FacebookServerSideEvent
 */
class FacebookServerSideEvent {
    const FALLBACK_ENDPOINT = 'https://demo-2.conversionsapigateway.com/';

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
            if ( self::should_suppress_frontend_send() ) {
                FacebookSignalState::queue_event( $event );
            } else {
                do_action(
                    'send_server_events',
                    array( $event ),
                    1
                );
            }
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
     * All CAPI event sending flows through this method.
     *
     * @param ServerEvent[] $events          The events to send.
     * @param string|null   $test_event_code Optional test event code.
     * @return array|null Result array with 'success' key, or null if skipped.
     */
    public static function send( $events, $test_event_code = null ) {
        $events = apply_filters( 'before_conversions_api_event_sent', $events );
        if ( empty( $events ) ) {
            return null;
        }

        if ( self::should_suppress_frontend_send() ) {
            foreach ( $events as $queued_event ) {
                FacebookSignalState::queue_event( $queued_event );
            }
            return null;
        }

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

        $request = ( new EventRequest( $pixel_id ) )
                ->setEvents( $events )
                ->setPartnerAgent( $agent );

        if ( $test_event_code ) {
            $request->setTestEventCode( $test_event_code );
        }

        try {
            $api      = Api::init( null, null, $access_token );
            $response = $request->execute();

            FacebookCapiCircuitBreaker::record_success();

            return array(
                'success'         => true,
                'events_received' => $response->getEventsReceived(),
            );
        } catch ( RequestException $e ) {
            FacebookCapiCircuitBreaker::record_exception( $e );
            if (
                $e instanceof AuthorizationException
                && '1' === FacebookWordpressOptions::get_capig()
            ) {
                self::send_to_fallback_endpoint( $request, $pixel_id );
            } else {
                // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( '[Facebook Pixel for WordPress] Send Events Exception: ' . $e->getMessage() );
                error_log( $e->getTraceAsString() );
                // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            return array(
                'success' => false,
                'error'   => array(
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
                ),
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
     * Retries the event payload against a token-less recovery endpoint.
     *
     * Invoked when the primary CAPI call raises an AuthorizationException
     * (invalid/expired token) and the Conversions API Gateway (CAPIG)
     * toggle is on.
     * The Graph API reports token errors by code/type rather than HTTP
     * status, so we key off the exception type, not 401/403. The same
     * normalized payload is re-sent, token-agnostically, to
     * `{base}/capi/{pixel_id}/events`.
     *
     * The base URL is overridable via the `fbwp_capi_fallback_endpoint`
     * filter so deployments can point at their own relay without code changes.
     *
     * @param EventRequest $request  The already-built request whose normalized
     *                               payload will be re-sent.
     * @param string       $pixel_id The dataset/pixel id for the endpoint path.
     */
    private static function send_to_fallback_endpoint( EventRequest $request, $pixel_id ) {
        $base_url = apply_filters(
            'fbwp_capi_fallback_endpoint',
            self::FALLBACK_ENDPOINT
        );
        if ( empty( $base_url ) ) {
            return;
        }

        $url = rtrim( $base_url, '/' ) . '/capi/' . $pixel_id . '/events';

        $response = wp_remote_post(
            $url,
            array(
                'body'    => wp_json_encode(
                    self::normalize_for_fallback( $request->normalize() )
                ),
                'headers' => array( 'Content-Type' => 'application/json' ),
                'timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[Facebook Pixel for WordPress] CAPI fallback failed: ' . $response->get_error_message() );
            // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    /**
     * Reshapes an SDK-normalized payload for the recovery gateway.
     *
     * The gateway validates more strictly than the Graph API: it rejects
     * `custom_data` serialized as an empty array (`[]`) and the null-valued
     * fields the SDK emits. For each event we drop null fields and coerce an
     * empty `custom_data` to an object so it serializes as `{}`.
     *
     * @param array $payload The `$request->normalize()` output.
     * @return array The gateway-compatible payload.
     */
    private static function normalize_for_fallback( $payload ) {
        if ( empty( $payload['data'] ) || ! is_array( $payload['data'] ) ) {
            return $payload;
        }
        foreach ( $payload['data'] as &$event ) {
            if ( ! is_array( $event ) ) {
                continue;
            }
            $event = array_filter(
                $event,
                static function ( $value ) {
                    return null !== $value;
                }
            );
            if ( empty( $event['custom_data'] ) ) {
                $event['custom_data'] = (object) array();
            }
        }
        unset( $event );
        return $payload;
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

    /**
     * Whether the current request should suppress frontend sends.
     *
     * @return bool
     */
    private static function should_suppress_frontend_send() {
        $is_admin_request = function_exists( 'is_admin' ) ? is_admin() : false;
        $is_cron_request  = function_exists( 'wp_doing_cron' ) ?
            wp_doing_cron() :
            false;

        return FacebookSignalState::is_held() &&
            ! $is_admin_request &&
            ! $is_cron_request;
    }
}
