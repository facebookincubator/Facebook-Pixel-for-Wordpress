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
class CapiSenderBase {
    /**
     * Lazily-initialized Conversions API client, reused across calls in the
     * same request (the access token is request-stable).
     *
     * @var \FacebookPixelPlugin\FacebookAds\Api|null
     */
    private $api = null;

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
        $events = apply_filters( 'before_conversions_api_event_sent', $events );
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

        return $request->execute();
    }
}
