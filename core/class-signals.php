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
 * Server-event service + cookie-backed frontend signal gating.
 *
 * Signals is a plain service the integrations call: it builds and tracks the
 * CAPI (server) event and, when the integration needs browser output, renders
 * that event to pixel code. It does NOT register integration hooks or decide
 * where the pixel goes — the integration owns its hooks, their signatures, and
 * what to do with the results, because that placement is integration-specific
 * (an AJAX POST response vs a redirect vs a page render).
 *
 * The consent-gating statics mirror WooCommerce\Facebook\Signals and the
 * frontend FacebookSignal JS API; keep the cookie name, states, and hold/
 * release semantics in sync with them.
 *
 * @see https://github.com/woocommerce/facebook-for-woocommerce/blob/trunk/includes/Signals.php
 * @see ../../js/facebook_signal.js
 */
class Signals {
    const COOKIE_NAME  = 'wc_facebook_signals_state';
    const AJAX_ACTION  = 'fbpix_set_pixel_signals';
    const NONCE_ACTION = 'fbpix_signals_state_nonce';
    const STATE_ACTIVE = 'active';
    const STATE_HELD   = 'held';

    /**
     * Store of server-side (CAPI) events for the current request.
     *
     * @var FacebookServerSideEvent
     */
    private $server_events;

    /**
     * The browser-side pixel generator.
     *
     * @var BrowserEvents
     */
    private $browser;

    /**
     * Constructor. Side-effect free (subsystem wiring is done via boot()), so
     * instances can be created freely. Signals is the facade over the whole
     * signals domain, so it resolves/holds the server + browser sides itself.
     */
    public function __construct() {
        $this->server_events = FacebookServerSideEvent::get_instance();
        $this->browser       = new BrowserEvents();
    }

    /**
     * Composition root for the signals subsystem. The plugin bootstrap calls
     * this once; nothing outside Signals references the collaborators directly.
     *
     * @return void
     */
    public function boot() {
        $this->browser->set_pixel_id(
            FacebookWordpressOptions::get_active_pixel_id()
        );
        $this->register();
        new ReleaseSignalsAjax( $this );
        new FacebookCapiEvent( $this );
        new ServerEventAsyncTask( $this );

        if ( $this->should_hold_signals() ) {
            $this->hold();
        }

        add_action( 'init', array( $this, 'inject_pixel' ), 0 );
        add_action( 'parse_request', array( $this, 'handle_open_bridge' ), 0 );
    }

    /**
     * Injects the browser pixel + wires integrations (hooked on `init`).
     *
     * @return void
     */
    public function inject_pixel() {
        ( new FacebookWordpressPixelInjection( $this, $this->browser ) )->inject();
    }

    /**
     * Handles an inbound Open Bridge request (hooked on `parse_request`).
     *
     * @return void
     */
    public function handle_open_bridge() {
        if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
            return;
        }
        $request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

        if (
            FacebookPluginUtils::ends_with(
                $request_uri,
                FacebookPluginConfig::OPEN_BRIDGE_PATH
            ) &&
            isset( $_SERVER['REQUEST_METHOD'] )
            && 'POST' === $_SERVER['REQUEST_METHOD']
        ) {
            $data = json_decode( file_get_contents( 'php://input' ), true );
            if ( ! is_null( $data ) ) {
                FacebookWordpressOpenBridge::get_instance( $this )
                    ->handle_open_bridge_req( $data );
            }
            if ( isset( $_SERVER['HTTP_ORIGIN'] ) ) {
                $origin = wp_kses( wp_unslash( $_SERVER['HTTP_ORIGIN'] ), array() );
                header( "Access-Control-Allow-Origin: $origin" );
                header( 'Access-Control-Allow-Credentials: true' );
                header( 'Access-Control-Max-Age: 86400' );
            }
            exit();
        }
    }

    /**
     * Registers the consent-state AJAX handler. Called by boot().
     *
     * @return void
     */
    public function register() {
        add_action(
            'wp_ajax_' . self::AJAX_ACTION,
            array( $this, 'handle_update_state' )
        );
        add_action(
            'wp_ajax_nopriv_' . self::AJAX_ACTION,
            array( $this, 'handle_update_state' )
        );
    }

    /**
     * Builds and tracks the server-side (CAPI) event for the given data and
     * returns it, so the caller can render it for the browser if needed.
     *
     * Empty fields (null / '') are dropped; 0 and '0' are kept. When the
     * integration supplies a shared id (e.g. a browser-generated id carried on
     * a form), it is set on the event so it deduplicates against the browser
     * event that uses the same id.
     *
     * @param string      $event_name      The event name (e.g. 'Lead').
     * @param array       $data            User + custom data, standard-keyed.
     * @param string      $tracking_name   The integration tracking name.
     * @param string|null $event_id        Optional shared id for dedup.
     * @param bool        $prefer_referrer Prefer the referrer as event source
     *                                     URL. Defaults to true.
     * @return \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event
     */
    public function track(
        $event_name,
        array $data,
        $tracking_name,
        $event_id = null,
        $prefer_referrer = true
    ) {
        $clean = array_filter(
            $data,
            static function ( $value ) {
                return null !== $value && '' !== $value;
            }
        );

        $event = ServerEventFactory::create_from_data(
            $event_name,
            $clean,
            $tracking_name,
            $prefer_referrer
        );

        if ( ! empty( $event_id ) ) {
            $event->setEventId( $event_id );
        }

        $this->server_events->track( $event );
        return $event;
    }

    /**
     * Renders a tracked event to browser pixel code. The integration decides
     * where the returned code goes (AJAX response, footer, inline, …).
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @param string                                                   $tracking_name Tracking name.
     * @param bool                                                     $script_tag Wrap in a <script> tag.
     * @return string
     */
    public function render( $event, $tracking_name, $script_tag = true ) {
        return $this->browser->render(
            array( $event ),
            $tracking_name,
            $script_tag
        );
    }

    /**
     * Sends any server-side events that were queued for deferred sending during
     * this request via the send_server_events action. Keeps the pending-event
     * store encapsulated so callers never reach into it.
     *
     * @return void
     */
    public function flush_pending_events() {
        $pending = $this->server_events->get_pending_events();
        if ( count( $pending ) > 0 ) {
            do_action( 'send_server_events', $pending, count( $pending ) );
        }
    }

    /**
     * Sends events straight to the Conversions API. The facade entry point for
     * one-off server sends (release flow, OpenBridge, CAPI event endpoint,
     * async task) so callers never touch the event store directly.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events The events.
     * @param string|null                                                $test_event_code Optional test code.
     * @return array|null
     */
    public function send( $events, $test_event_code = null ) {
        return $this->server_events->send( $events, $test_event_code );
    }

    /*
     * Temporary redirects to the CAPI event store.
     *
     * Legacy integrations and PixelInjection call these through Signals so the
     * store is reached only via the facade. They are thin pass-throughs on
     * purpose and are removed as each caller is converted to
     * track()/render()/flush_pending_events(). Do not add new callers.
     */

    /**
     * Tracks an already-built server event.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @param bool                                                     $send_now Send now or queue.
     * @return void
     */
    public function track_event( $event, $send_now = true ) {
        $this->server_events->track( $event, $send_now );
    }

    /**
     * Returns all events tracked this request.
     *
     * @return array
     */
    public function get_tracked_events() {
        return $this->server_events->get_tracked_events();
    }

    /**
     * Returns events queued for deferred sending this request.
     *
     * @return array
     */
    public function get_pending_events() {
        return $this->server_events->get_pending_events();
    }

    /**
     * Stores a server event to be emitted when a named callback fires.
     *
     * @param string                                                   $callback_name The callback name.
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event         The event.
     * @return void
     */
    public function set_pending_pixel_event( $callback_name, $event ) {
        $this->server_events->set_pending_pixel_event( $callback_name, $event );
    }

    /**
     * Returns a server event stored for a named callback.
     *
     * @param string $callback_name The callback name.
     * @return \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event|null
     */
    public function get_pending_pixel_event( $callback_name ) {
        return $this->server_events->get_pending_pixel_event( $callback_name );
    }

    /**
     * Whether signals are held for the current request (consent not yet given).
     *
     * @return bool
     */
    public function is_held() {
        return FacebookSignalState::is_held();
    }

    /**
     * Hold signals for the current request (queue instead of send).
     *
     * @return void
     */
    public function hold() {
        FacebookSignalState::hold();
    }

    /**
     * Release signals for the current request.
     *
     * @return void
     */
    public function release() {
        FacebookSignalState::release();
    }

    /**
     * Returns a CAPI event queued while signals were held, by event id.
     *
     * @param string $event_id Event identifier.
     * @return \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event|null
     */
    public function get_queued_event( $event_id ) {
        return FacebookSignalState::get_queued_event( $event_id );
    }

    /**
     * Returns the forward-safe normalized user data for a queued event.
     *
     * @param string $event_id Event identifier.
     * @return array|null
     */
    public function get_queued_user_data( $event_id ) {
        return FacebookSignalState::get_queued_user_data( $event_id );
    }

    /**
     * Returns attribution data (e.g. fbp/fbc) captured while held.
     *
     * @param string $key Data key.
     * @return string|null
     */
    public function get_attribution_data( $key ) {
        return FacebookSignalState::get_attribution_data( $key );
    }

    /**
     * Clears the queued events and attribution stores.
     *
     * @return void
     */
    public function reset_queue() {
        FacebookSignalState::reset_queue();
    }

    /**
     * Get current signals state.
     *
     * @return string|null 'active', 'held', or null when unset.
     */
    public function get_signal_state() {
        if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return null;
        }

        $state = sanitize_text_field(
            wp_unslash( $_COOKIE[ self::COOKIE_NAME ] )
        );

        if ( in_array( $state, array( self::STATE_ACTIVE, self::STATE_HELD ), true ) ) {
            return $state;
        }

        return null;
    }

    /**
     * Whether signals are currently active.
     *
     * @return bool
     */
    public function is_signals_active() {
        return self::STATE_ACTIVE === $this->get_signal_state();
    }

    /**
     * Whether signals should be held.
     *
     * @return bool
     */
    public function should_hold_signals() {
        return self::STATE_HELD === $this->get_signal_state();
    }

    /**
     * Persist the signal state via AJAX.
     *
     * Expected POST params:
     *  - security : nonce
     *  - state    : 'active' or 'held'
     *
     * @return void
     */
    public function handle_update_state() {
        check_ajax_referer( self::NONCE_ACTION, 'security' );

        $state = self::normalize_state(
            isset( $_POST['state'] )
                ? sanitize_text_field( wp_unslash( $_POST['state'] ) )
                : null
        );

        if ( self::STATE_HELD === $state ) {
            $this->hold();
        } else {
            $this->release();
        }

        wp_send_json_success(
            array(
                'state' => $state,
            )
        );
    }

    /**
     * Normalize an incoming state value to a canonical STATE_ACTIVE / STATE_HELD.
     *
     * Anything that is not exactly 'active' (case-insensitive) becomes 'held'.
     *
     * @param string|null $raw Raw input value.
     *
     * @return string
     */
    private static function normalize_state( $raw ) {
        if ( null === $raw ) {
            return self::STATE_HELD;
        }

        $candidate = strtolower( $raw );

        return self::STATE_ACTIVE === $candidate ? self::STATE_ACTIVE : self::STATE_HELD;
    }
}
