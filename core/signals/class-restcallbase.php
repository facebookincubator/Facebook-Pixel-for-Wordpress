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
 * Base for Conversions API callers.
 *
 * Owns the shared call flow as a template method: check configuration, build
 * the EventRequest, execute it, and route the outcome through hooks the
 * subclasses fill in. It knows nothing about the circuit breaker or the shape
 * of the result — subclasses decide those (e.g. ServerEventSender engages the
 * breaker and returns nothing; AdminEventSender skips the breaker and returns
 * the result for the admin panel).
 *
 * @internal Not part of the plugin's public API.
 */
abstract class RestCallBase {
    /**
     * Lazily-initialized Conversions API client, reused across calls in the
     * same request (the access token is request-stable).
     *
     * @var \FacebookPixelPlugin\FacebookAds\Api|null
     */
    private $api = null;

    /**
     * Template method: run the REST call and route the outcome through the
     * subclass hooks.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events          The events.
     * @param string|null                                                $test_event_code Optional test event code.
     * @return mixed Whatever the subclass hooks return.
     */
    protected function call( $events, $test_event_code = null ) {
        if ( ! $this->has_config() ) {
            return $this->handle_missing_config();
        }

        if ( ! $this->can_call() ) {
            return $this->handle_blocked();
        }

        try {
            $request  = $this->build_request( $events, $test_event_code );
            $response = $request->execute();
            return $this->handle_success( $response );
        } catch ( \Exception $e ) {
            return $this->handle_error( $e );
        }
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
     * Builds the Conversions API request for the given events.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events          The events.
     * @param string|null                                                $test_event_code Optional test event code.
     * @return \FacebookPixelPlugin\FacebookAds\Object\ServerSide\EventRequest
     */
    protected function build_request( $events, $test_event_code = null ) {
        $pixel_id     = FacebookWordpressOptions::get_active_pixel_id();
        $access_token = FacebookWordpressOptions::get_active_access_token();

        if ( null === $this->api ) {
            $this->api = Api::init( null, null, $access_token );
        }

        $request = ( new EventRequest( $pixel_id ) )
            ->setEvents( $events )
            ->setPartnerAgent( $this->partner_agent( $events ) );

        if ( $test_event_code ) {
            $request->setTestEventCode( $test_event_code );
        }

        return $request;
    }

    /**
     * The partner agent string to stamp on the request. Base uses the plain
     * configured agent; subclasses may decorate it using the events.
     *
     * @return string
     */
    protected function partner_agent() {
        return FacebookWordpressOptions::get_agent_string();
    }

    /**
     * Whether the call may proceed. Base always allows; subclasses gate here
     * (e.g. on the circuit breaker).
     *
     * @return bool
     */
    protected function can_call() {
        return true;
    }

    /**
     * Outcome when the pixel id / access token are missing. Base does nothing.
     *
     * @return mixed
     */
    protected function handle_missing_config() {
        return null;
    }

    /**
     * Outcome when can_call() blocked the call. Base does nothing.
     *
     * @return mixed
     */
    protected function handle_blocked() {
        return null;
    }

    /**
     * Handles a successful Conversions API response.
     *
     * @param mixed $response The EventResponse.
     * @return mixed
     */
    abstract protected function handle_success( $response );

    /**
     * Handles a failed Conversions API call.
     *
     * @param \Exception $e The caught exception.
     * @return mixed
     */
    abstract protected function handle_error( \Exception $e );
}
