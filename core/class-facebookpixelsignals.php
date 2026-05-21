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
    const COOKIE_NAME  = 'fbpix_pixel_signals';
    const AJAX_ACTION  = 'fbpix_set_pixel_signals';
    const NONCE_ACTION = 'fbpix_pixel_signals_nonce';

    /**
     * Register AJAX handlers.
     */
    public function __construct() {
        add_action(
            'wp_ajax_' . self::AJAX_ACTION,
            array( $this, 'handle_set_signals' )
        );
        add_action(
            'wp_ajax_nopriv_' . self::AJAX_ACTION,
            array( $this, 'handle_set_signals' )
        );
    }

    /**
     * Get current signals state.
     *
     * @return bool|null
     */
    public static function get_signals_state() {
        if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return null;
        }

        return '1' === sanitize_text_field(
            wp_unslash( $_COOKIE[ self::COOKIE_NAME ] )
        );
    }

    /**
     * Whether tracking should be paused.
     *
     * @return bool
     */
    public static function should_pause_tracking() {
        return false === self::get_signals_state();
    }

    /**
     * Persist signals via AJAX.
     *
     * @return void
     */
    public function handle_set_signals() {
        check_ajax_referer( self::NONCE_ACTION, 'security' );

        $granted = isset( $_POST['granted'] ) &&
            '1' === sanitize_text_field( wp_unslash( $_POST['granted'] ) );

        $this->set_cookie( $granted );
        $_COOKIE[ self::COOKIE_NAME ] = $granted ? '1' : '0';

        wp_send_json_success(
            array(
                'granted' => $granted,
            )
        );
    }

    /**
     * Set signals cookie.
     *
     * @param bool $granted Whether signals is granted.
     *
     * @return void
     */
    private function set_cookie( $granted ) {
        setcookie(
            self::COOKIE_NAME,
            $granted ? '1' : '0',
            time() + YEAR_IN_SECONDS,
            defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
            defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
            is_ssl(),
            false
        );
    }
}
