<?php
/**
 * Facebook Pixel Plugin CapiSenderBase class.
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

use Exception as ConfigNotFoundException;
use FacebookPixelPlugin\FacebookAds\Api;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\EventRequest;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Base class that builds and sends Conversions API requests.
 *
 * Handles lazy initialization of the Facebook Ads API client, configuration
 * checks (pixel id / access token), and construction of the EventRequest.
 */
abstract class CapiSenderBase {
    /**
     * Lazily-initialized Conversions API client, reused across calls in the
     * same request (the access token is request-stable).
     *
     * @var \FacebookPixelPlugin\FacebookAds\Api|null
     */
    private $api = null;

    /**
     * The events the last send_request() call actually handed to the API,
     * after the before_conversions_api_event_sent filter ran. Empty when the
     * filter removed everything or no request has been made yet.
     *
     * @var \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[]
     */
    protected $last_sent_events = array();

    /**
     * Event ids of the last batch handed to the API (see $last_sent_events).
     *
     * @return string[]
     */
    protected function last_sent_event_ids() {
        $ids = array();
        foreach ( $this->last_sent_events as $event ) {
            if ( ! is_object( $event ) || ! method_exists( $event, 'getEventId' ) ) {
                continue;
            }
            $id = $event->getEventId();
            if ( is_string( $id ) && '' !== $id ) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * Whether the pixel id and access token are configured.
     *
     * @return bool
     */
    protected function has_config() {
        return ! empty( FacebookWordpressOptions::get_active_pixel_id() )
            && ! empty( FacebookWordpressOptions::get_active_access_token() );
    }

    /**
     * Creates (once per request) and returns the Conversions API client.
     *
     * @throws ConfigNotFoundException If the pixel id / access token is not configured.
     * @return \FacebookPixelPlugin\FacebookAds\Api
     */
    protected function create_api() {

        if ( ! $this->has_config() ) {
            throw new ConfigNotFoundException( 'A valid Pixel id is required for S2S events to work.' );
        }

        if ( null === $this->api ) {
            $this->api = Api::init(
                null,
                null,
                FacebookWordpressOptions::get_active_access_token()
            );
        }
        return $this->api;
    }

    /**
     * Sends the given events to the Conversions API.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events The events to send.
     * @return mixed The send result, when the implementation produces one.
     */
    abstract public function send( array $events );

    /**
     * Builds and sends the Conversions API request for the given events and
     * returns the API response. Throws on transport / API error.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events          The events.
     * @param string                                                     $agent           Partner agent for the events.
     * @param string|null                                                $test_event_code Optional test event code.
     * @throws ConfigNotFoundException If the pixel id / access token is not configured.
     * @return \FacebookPixelPlugin\FacebookAds\Object\ServerSide\EventResponse|null
     *         The API response, or null when the before_conversions_api_event_sent
     *         filter removed every event (nothing sent).
     */
    protected function send_request( $events, $agent, $test_event_code = null ) {
        $events                 = apply_filters( 'before_conversions_api_event_sent', $events );
        $this->last_sent_events = is_array( $events ) ? array_values( $events ) : array();
        if ( empty( $events ) ) {
            return null;
        }

        $this->create_api();

        if ( ! $this->has_config() ) {
            throw new ConfigNotFoundException( 'A valid access token is required for S2S events to work.' );
        }

        $request = ( new EventRequest(
            FacebookWordpressOptions::get_active_pixel_id()
        ) )
            ->setEvents( $events )
            ->setPartnerAgent( $agent );

        if ( $test_event_code ) {
            $request->setTestEventCode( $test_event_code );
        }

        $response = $this->execute_request( $request );

        // E2E instrumentation only: record the transformed events that were
        // actually sent so the Playwright suite can assert Pixel<->CAPI
        // deduplication. Gated + no-op in production (see the method's docblock).
        FacebookServerSideEvent::maybe_log_events_for_tests( $events );

        return $response;
    }

    /**
     * Executes a built request against the API. Separate so tests can stand
     * in for the network call.
     *
     * @param EventRequest $request The request to execute.
     * @return \FacebookPixelPlugin\FacebookAds\Object\ServerSide\EventResponse
     */
    protected function execute_request( EventRequest $request ) {
        return $request->execute();
    }
}
