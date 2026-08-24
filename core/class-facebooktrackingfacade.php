<?php
/**
 * Facebook Pixel Plugin FacebookTrackingFacade class.
 *
 * This file contains the tracking facade that fronts the Pixel/CAPI transports.
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

use Exception;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

use FacebookPixelPlugin\Core\ServerEventFactory;

/**
 * Tracking facade for the signals subsystem.
 *
 * Fronts the browser Pixel and server-side CAPI transports behind one object.
 * Integrations depend on a FacebookTrackingFacade instance (injected via
 * TrackableIntegrationBase) to build and dispatch events.
 */
class FacebookTrackingFacade {

    const FB_INTEGRATION_TRACKING_KEY = FacebookPixel::FB_INTEGRATION_TRACKING_KEY;

    /**
     * Browser delivery mode: enqueue the browser event for the footer flush.
     * Use for non-AJAX page renders.
     *
     * @var string
     */
    const BROWSER_INLINE = 'inline';

    /**
     * Browser delivery mode: hand the browser event to the configured AJAX
     * channel (injected into the host plugin's AJAX response / cart fragments).
     * Use for AJAX submits.
     *
     * @var string
     */
    const BROWSER_AJAX = 'channel';

    /**
     * Browser delivery mode: no browser event (a client-side script fires the
     * Pixel itself).
     *
     * @var string
     */
    const BROWSER_NONE = 'none';

    /**
     * Server delivery mode: send the event to CAPI synchronously in-request.
     *
     * @var string
     */
    const SERVER_SYNC = 'server_sync';

    /**
     * Server delivery mode: send the event to CAPI asynchronously. Not yet
     * implemented.
     *
     * @var string
     */
    const SERVER_ASYNC = 'server_async';

    /**
     * Server delivery mode: do not send the event to CAPI.
     *
     * @var string
     */
    const SERVER_NONE = 'server_none';

    /**
     * The partner agent string sent with events.
     *
     * @var string
     */
    public $agent_string;

    /**
     * The active Meta Pixel id.
     *
     * @var string
     */
    public $pixel_id;

    /**
     * The Conversions API (server-side) transport.
     *
     * @var Capi
     */
    private Capi $capi;

    /**
     * The browser Pixel transport.
     *
     * @var Pixel
     */
    private Pixel $pixel;

    /**
     * Wires up the facade from the given tracking configuration and constructs
     * the CAPI and browser Pixel transports.
     *
     * @param string $agent_string    The partner agent string.
     * @param string $active_pixel_id The active Meta Pixel id.
     */
    public function __construct( $agent_string, $active_pixel_id ) {
        $this->agent_string = $agent_string;
        $this->pixel_id     = $active_pixel_id;

        $this->capi  = new Capi( new CircuitBreakerAwareSyncCapiSender( new Logger() ), new AsyncCapiSender() );
        $this->pixel = new Pixel( $this->agent_string, $this->pixel_id );
    }

    /**
     * Initializes the pixel, signals subsystem and pixel injection.
     *
     * @return void
     */
    public function initialize() {

        new ServerEventAsyncTask( $this );
        FacebookPixel::initialize( $this->pixel_id );
        new Signals();
        new ReleaseSignalsAjax( $this->capi );

        if ( Signals::should_hold_signals() ) {
            FacebookSignalState::hold();
        }

        // Initialize ParamBuilder server-side before pixel injection.
        FacebookParamBuilder::server_setup();

        add_action( 'init', array( $this, 'register_pixel_injection' ), 0 );
    }

    /**
     * Registers the pixel injection. This method instantiates the
     * FacebookWordpressPixelInjection and calls its inject method.
     *
     * The inject method is responsible for adding the necessary hooks to
     * inject the Facebook pixel code into the footer of the WordPress page.
     */
    public function register_pixel_injection() {
        $injection_obj = new FacebookWordpressPixelInjection();
        $injection_obj->inject();
    }

    /**
     * Builds a server event from normalized event data.
     *
     * Routes through ServerEventFactory::safe_create_event so the data is split
     * into user_data / custom_data, user data is AAM-normalized, and the
     * fb_integration_tracking custom property is added at creation time. Because
     * the property lives on the event's custom_data from the start, both the CAPI
     * and Pixel transports pick it up without needing the integration name.
     *
     * @param string      $event_name  The Meta event name (e.g. 'Lead').
     * @param array       $data        Normalized event data (user_data + custom_data keys).
     * @param string|null $integration The integration name for fb_integration_tracking.
     * @param bool        $prefer_referrer_for_event_src Whether to use the referrer
     *                    URL (instead of the current request URL) as the event
     *                    source URL. Default true, which suits AJAX/form submits
     *                    (fired on admin-ajax/REST, where the referrer is the real
     *                    page). Pass false for non-AJAX page-render events (e.g.
     *                    WooCommerce/EDD ViewContent/InitiateCheckout/Purchase),
     *                    where the current URL is the actual page.
     * @return \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event
     */
    public function generate_event( $event_name, array $data = array(), $integration = null, $prefer_referrer_for_event_src = true ) {
        return ServerEventFactory::safe_create_event(
            $event_name,
            function () use ( $data ) {
                return $data;
            },
            array(),
            $integration,
            $prefer_referrer_for_event_src
        );
    }

    /**
     * Sends the event to CAPI for the given server mode. When signals are held
     * on a front-end request the event is queued for later release instead.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event  The event.
     * @param string                                                   $server SERVER_SYNC, SERVER_ASYNC or SERVER_NONE.
     * @throws \Exception When the server delivery mode is not supported.
     * @return void
     */
    public function track_server_event( $event, $server = self::SERVER_SYNC ) {
        if ( ! $this->is_enabled() ) {
            return;
        }

        if ( self::SERVER_NONE === $server ) {
            return;
        }
        if ( self::should_suppress_frontend_send() ) {
            FacebookSignalState::queue_event( $event );
        } else {
            switch ( $server ) {
                case self::SERVER_SYNC:
                    $this->capi->send( array( $event ), Capi::CAPI_SYNC );
                    break;
                case self::SERVER_ASYNC:
                    $this->capi->send( array( $event ), Capi::CAPI_ASYNC );
                    break;
                default:
                    throw new \Exception( esc_html( $server ) . ' is not implemented' );

            }
        }
    }

    /**
     * Enqueues the event with the browser Pixel for the footer flush.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @return void
     */
    public function track_inline_browser_event( $event ) {

        if ( ! $this->is_enabled() ) {
            return;
        }

        $this->pixel->enqueue( $event );
    }

    /**
     * Registers raw footer markup (e.g. an AJAX container element) with the
     * browser Pixel to be printed on wp_footer.
     *
     * @param string $container The markup to print in the footer.
     * @return void
     */
    public function register_ajax_dom_container( $container ) {
        $this->pixel->register_ajax_dom_element( $container );
    }

    /**
     * Generates the browser Pixel script for an event: the queue script when
     * signals are held, otherwise the normal fbq() script.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event              The event.
     * @param bool                                                     $include_script_tag Whether to wrap the code in a <script> element.
     * @return string The generated Pixel script.
     */
    public function generate_pixel_code( $event, $include_script_tag ) {

        if ( FacebookSignalState::is_held() ) {
            return $this->pixel->generate_queue_script_for_event(
                $event,
                $include_script_tag
            );
        } else {
            return $this->pixel->generate_script_for_event(
                $event,
                $include_script_tag
            );
        }
    }

    /**
     * Whether the current request should suppress sending (queue events for
     * later release instead). True only for a held front-end request; admin and
     * cron requests always send, so backend and scheduled events are not gated
     * by the visitor's consent hold.
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

    /**
     * Whether the signals subsystem is enabled.
     *
     * @return bool True when signals tracking is active.
     */
    public function is_enabled() {
        return ! empty( FacebookWordpressOptions::get_active_pixel_id() )
            && ! empty( FacebookWordpressOptions::get_active_access_token() );
    }

    /**
     * Sets up the default signal behavior for the integration.
     *
     * Intended to create the frontend and backend PageView events, configure the
     * ParamBuilder, and wire up the hold/release behavior. Not yet implemented.
     *
     * @throws \Exception Always, until this is implemented.
     * @return void
     */
    public function defaults() {
        // Create the PageView events for frontend & backend.
        // ParamBuilder setup.
        // Hold/Release setup.
        // TODO: Test if sending CAPI events is causing errors => unsupported parameters such as Tracking Integration.
        throw new \Exception( 'Not implemented' );
    }
}
