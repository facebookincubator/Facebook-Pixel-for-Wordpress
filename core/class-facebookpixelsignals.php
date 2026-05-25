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
 */
class FacebookPixelSignals {
    const COOKIE_NAME  = 'wc_facebook_signals_state';
    const AJAX_ACTION  = 'fbpix_set_pixel_signals';
    const NONCE_ACTION = 'fbpix_pixel_signals_nonce';
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

        $raw_state = isset( $_POST['state'] )
            ? strtolower( sanitize_text_field( wp_unslash( $_POST['state'] ) ) )
            : self::STATE_HELD;

        $state = self::STATE_ACTIVE === $raw_state ? self::STATE_ACTIVE : self::STATE_HELD;

        $this->set_cookie( $state );
        $_COOKIE[ self::COOKIE_NAME ] = $state;

        wp_send_json_success(
            array(
                'state' => $state,
            )
        );
    }

    /**
     * Set the signal state cookie.
     *
     * @param string $state Signal state ('active' or 'held').
     *
     * @return void
     */
    private function set_cookie( $state ) {
        setcookie(
            self::COOKIE_NAME,
            $state,
            time() + YEAR_IN_SECONDS,
            defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
            defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
            is_ssl(),
            false
        );
    }
}
