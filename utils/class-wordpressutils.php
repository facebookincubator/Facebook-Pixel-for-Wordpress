<?php
/**
 * Facebook Pixel Plugin WordPressUtils class.
 *
 * This file contains helper methods that wrap core WordPress functions used
 * across the plugin.
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

declare( strict_types=1 );

namespace FacebookPixelPlugin\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps core WordPress functions used across the plugin.
 */
class WordPressUtils {

    /**
     * Reads and sanitizes a single value from $_POST.
     *
     * This is a low-level reader: it unslashes and sanitizes the value but does
     * NOT perform nonce/CSRF verification, because whether that is required
     * depends on the caller's context:
     *
     *  - Reading $_POST on an AJAX endpoint you own, to drive a state change or
     *    act on user-supplied data: call {@see self::verify_ajax_request()}
     *    first, then read with this method.
     *  - Reading data already validated by a third-party form plugin (its hook
     *    fires only after it has verified its own nonce), or emitting a
     *    read-only tracking side effect (no state change, so no CSRF surface,
     *    and a nonce baked into a cached page would be stale): no nonce check is
     *    needed and this method may be used directly.
     *
     * @param string $param_name The $_POST field name to read.
     * @return string|null The sanitized value, or null if the field is absent.
     */
    public static function get_from_post( $param_name ) {
        // Nonce verification is intentionally the caller's responsibility here;
        // see the method docblock and self::verify_ajax_request().
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_POST[ $param_name ] ) ) {
            return null;
        }
        $value = sanitize_text_field( wp_unslash( $_POST[ $param_name ] ) );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        return $value;
    }

    /**
     * Verifies the nonce for an AJAX request. Works for both logged-in (priv)
     * and logged-out (nopriv) requests.
     *
     * Call this at the start of an AJAX handler you own, BEFORE reading $_POST,
     * whenever the request drives a state change or acts on user-supplied data.
     *
     * Do NOT use it when:
     *  - the data was handed to you by another plugin's hook (that plugin has
     *    already verified its own nonce), or
     *  - the handler only produces a read-only tracking side effect (no state
     *    change means no CSRF surface, and a nonce embedded in a cached page
     *    would be stale) — in those cases sanitize the input and skip the nonce.
     *
     * Note: for nopriv (anonymous) requests a WordPress nonce is not bound to a
     * user and therefore provides weaker CSRF protection than for logged-in
     * users; it remains the standard mechanism.
     *
     * @param string $action    The nonce action string.
     * @param string $query_arg The request field carrying the nonce. Default 'nonce'.
     * @return bool True if the nonce is valid, false otherwise.
     */
    public static function verify_ajax_request( $action, $query_arg = 'nonce' ) {
        return false !== check_ajax_referer( $action, $query_arg, false );
    }

    /**
     * Returns the current user's personally identifiable information.
     *
     * @return array The user info extracted from the session.
     */
    public static function get_user_info() {
        $current_user = wp_get_current_user();
        if ( empty( $current_user ) ) {
            return array();
        }
        $event_data = array(
            'email'      => $current_user->user_email,
            'first_name' => $current_user->user_firstname,
            'last_name'  => $current_user->user_lastname,
            'id'         => $current_user->ID,
        );
        $user_id    = get_current_user_id();
        if ( 0 === $user_id ) {
            // Guest: drop the empty email/name and id=0 so we don't emit bogus
            // PII (e.g. an external_id of "0" shared across all logged-out users).
            return array_filter( $event_data );
        }
        $event_data['city']    = get_user_meta(
            $user_id,
            'billing_city',
            true
        );
        $event_data['zip']     = get_user_meta(
            $user_id,
            'billing_postcode',
            true
        );
        $event_data['country'] = get_user_meta(
            $user_id,
            'billing_country',
            true
        );
        $event_data['state']   = get_user_meta(
            $user_id,
            'billing_state',
            true
        );
        $event_data['phone']   = get_user_meta(
            $user_id,
            'billing_phone',
            true
        );
        return array_filter( $event_data );
    }

    /**
     * Registers the same filter callback on multiple filter hooks.
     *
     * @param string[] $filters  The filter hook names to register on.
     * @param callable $callback The callback to add to each filter.
     * @param int      $priority The filter priority. Default 20.
     * @return void
     */
    public static function register_filter_hooks( array $filters, $callback, $priority = 20 ) {
        foreach ( $filters as $filter ) {
            add_filter(
                $filter,
                $callback,
                $priority
            );
        }
    }
}
