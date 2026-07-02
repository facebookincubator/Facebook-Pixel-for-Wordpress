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
 * Cookie-backed signals state for frontend event gating.
 *
 * Mirrors WooCommerce\Facebook\Signals in the facebook-for-woocommerce plugin
 * and the frontend FacebookSignal JS API. These implementations share the
 * cookie name, state values, AJAX payload, and hold/release semantics; keep
 * them behaviorally in sync when making changes here.
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
     * Register AJAX handlers.
     *
     * @param FacebookServerSideEvent|null $server_events Optional CAPI event
     *   store. Defaults to the shared instance. Injected so integrations and
     *   the footer/async sender share one store per request.
     */
    public function __construct( $server_events = null ) {
        $this->server_events = $server_events instanceof FacebookServerSideEvent
            ? $server_events
            : FacebookServerSideEvent::get_instance();

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
     * Sends any server-side events that were queued (dispatched for deferred
     * sending) during this request via the send_server_events action. The
     * pending-event store is kept fully encapsulated here so callers never
     * reach into it.
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
     * Registers an event: when $hook fires, extract an EventData via
     * $data_provider, dispatch it (CAPI), and arrange browser delivery via
     * $delivery. The integration decides the hook and the event; Signals owns
     * all server-vs-browser handling.
     *
     * @param string        $hook          The WordPress hook (action/filter).
     * @param string        $event_name    The event name (e.g. 'Lead').
     * @param callable      $data_provider fn( ...$hook_args ): ?EventData.
     * @param EventDelivery $delivery      Browser-pixel delivery strategy.
     * @param string        $tracking_name The integration tracking name.
     * @param int           $priority      Hook priority. Defaults to 10.
     * @param int           $accepted_args Hook args to accept. Defaults to 2.
     * @return void
     */
    public function on(
        $hook,
        $event_name,
        $data_provider,
        $delivery,
        $tracking_name,
        $priority = 10,
        $accepted_args = 2
    ) {
        if ( $delivery instanceof EventDelivery ) {
            $delivery->register( $tracking_name );
        }

        // Thin forwarder: WordPress passes a per-hook argument list, so the
        // closure only captures those and hands them to the named handler
        // below along with this registration's config.
        add_filter(
            $hook,
            function ( ...$hook_args ) use (
                $event_name,
                $data_provider,
                $tracking_name,
                $delivery
            ) {
                return $this->handle_registered_hook(
                    $event_name,
                    $data_provider,
                    $tracking_name,
                    $delivery,
                    $hook_args
                );
            },
            $priority,
            $accepted_args
        );
    }

    /**
     * Handles a hook registered via on(): extracts the EventData from the
     * integration's data provider and dispatches it. Internal users are
     * skipped, and the hook's first argument is returned unchanged so this is
     * safe on both actions and filters.
     *
     * @param string        $event_name    The event name to dispatch.
     * @param callable      $data_provider fn( ...$hook_args ): ?EventData.
     * @param string        $tracking_name The integration tracking name.
     * @param EventDelivery $delivery      The browser delivery to queue onto.
     * @param array         $hook_args     Arguments WordPress passed to the hook.
     * @return mixed The hook's first argument, unchanged.
     */
    private function handle_registered_hook(
        $event_name,
        $data_provider,
        $tracking_name,
        $delivery,
        $hook_args
    ) {
        $passthrough = isset( $hook_args[0] ) ? $hook_args[0] : null;

        if ( FacebookPluginUtils::is_internal_user() ) {
            return $passthrough;
        }

        $event_data = call_user_func_array( $data_provider, $hook_args );
        if ( $event_data instanceof EventData && ! $event_data->is_empty() ) {
            $event = $this->dispatch( $event_name, $event_data, $tracking_name );
            if ( $delivery instanceof EventDelivery ) {
                $delivery->queue( $event );
            }
        }

        return $passthrough;
    }

    /**
     * Dispatches an EventData to the Conversions API (server side) by building
     * the Event and tracking it, and returns the Event so the caller can queue
     * it onto a browser delivery. Browser rendering is owned by the delivery.
     *
     * @param string    $event_name      The event name.
     * @param EventData $event_data      The neutral event payload.
     * @param string    $tracking_name   The integration tracking name.
     * @param bool      $prefer_referrer Whether to prefer the referrer as the
     *                                   event source URL. Defaults to true.
     * @return \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event
     */
    public function dispatch(
        $event_name,
        EventData $event_data,
        $tracking_name,
        $prefer_referrer = true
    ) {
        $event = ServerEventFactory::create_from_data(
            $event_name,
            $event_data->to_array(),
            $tracking_name,
            $prefer_referrer
        );

        // When an integration supplies a shared id (e.g. from a hidden form
        // field), reuse it so the server event deduplicates against the
        // browser event that carries the same id.
        $event_id = $event_data->get_event_id();
        if ( ! empty( $event_id ) ) {
            $event->setEventId( $event_id );
        }

        $this->server_events->track( $event );
        return $event;
    }

    /**
     * Get current signals state.
     *
     * @return string|null 'active', 'held', or null when unset.
     */
    public static function get_signal_state() {
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
    public static function is_signals_active() {
        return self::STATE_ACTIVE === self::get_signal_state();
    }

    /**
     * Whether signals should be held.
     *
     * @return bool
     */
    public static function should_hold_signals() {
        return self::STATE_HELD === self::get_signal_state();
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
            FacebookSignalState::hold();
        } else {
            FacebookSignalState::release();
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
