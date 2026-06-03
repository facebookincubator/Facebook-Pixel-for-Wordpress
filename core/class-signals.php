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
     * Register AJAX handlers.
     */
    public function __construct() {
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
