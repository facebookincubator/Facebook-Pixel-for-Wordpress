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
 * Class ServerEventSender
 *
 * Sends server-side (CAPI) events to the Conversions API as fire-and-forget: it
 * engages the circuit breaker and returns nothing. Consent gating and the
 * before_conversions_api_event_sent filter live in Signals; this class assumes
 * it is only handed events that should actually be sent. Reached only through
 * Signals.
 *
 * @internal Not part of the plugin's public API; use the Signals facade.
 */
class ServerEventSender extends RestCallBase {
    /**
     * Sends a list of events to the Conversions API. Fire-and-forget.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events The events to send.
     * @return void
     */
    public function send( $events ) {
        $this->call( $events );
    }

    /**
     * Suffixes the partner agent with '_ob' for OpenBridge events.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events The events.
     * @return string
     */
    protected function partner_agent( $events = array() ) {
        $agent = parent::partner_agent();
        if ( self::is_open_bridge_event( $events ) ) {
            $agent .= '_ob';
        }
        return $agent;
    }

    /**
     * Gates sending on the circuit breaker.
     *
     * @return bool
     */
    protected function can_call() {
        return FacebookCapiCircuitBreaker::is_send_allowed();
    }

    /**
     * Records the successful send with the circuit breaker.
     *
     * @param mixed $response The EventResponse.
     * @return void
     */
    protected function handle_success( $response ) {
        FacebookCapiCircuitBreaker::record_success();
    }

    /**
     * Records the failure with the circuit breaker and logs it.
     *
     * @param \Exception $e The caught exception.
     * @return void
     */
    protected function handle_error( \Exception $e ) {
        FacebookCapiCircuitBreaker::record_exception( $e );
        // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( '[Facebook Pixel for WordPress] CAPI error: ' . $e->getMessage() );
        error_log( $e->getTraceAsString() );
        // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
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
}
