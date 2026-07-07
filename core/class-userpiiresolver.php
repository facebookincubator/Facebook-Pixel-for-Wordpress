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
 * Resolves the current request's user identifiers (IP, user agent, fbp, fbc).
 *
 * This is the single source of the request-scoped PII/attribution that goes on
 * a server event's UserData. EventFactory (and the release flow) pull these
 * values from here rather than re-implementing cookie/query/session lookups.
 */
class UserPiiResolver {
    /**
     * Scans the HTTP headers for the first valid IP address it can find.
     *
     * @return string|null The first valid IP address found, or null if none
     *                     were found.
     */
    public static function get_ip_address() {
        $headers_to_scan = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ( $headers_to_scan as $header ) {
            if ( isset( $_SERVER[ $header ] ) ) {
                $ip_list = explode( ',', $_SERVER[ $header ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
                foreach ( $ip_list as $ip ) {
                    $trimmed_ip = trim( $ip );
                    if ( self::is_valid_ip_address( $trimmed_ip ) ) {
                        return $trimmed_ip;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Retrieves the User-Agent string from the HTTP request headers.
     *
     * @return string|null The User-Agent string, or null if it was not found.
     */
    public static function get_http_user_agent() {
        $user_agent = null;

        if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            $user_agent = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        }

        return $user_agent;
    }

    /**
     * Retrieves the Facebook Browser ID (FBP).
     *
     * Resolution order:
     * 1. ParamBuilder server-side extraction
     * 2. _fbp cookie (set by fbevents.js)
     *
     * @return string|null The FBP value, or null if unavailable.
     */
    public static function get_fbp() {
        $fbp = FacebookParamBuilder::get_fbp();

        if ( empty( $fbp ) && ! empty( $_COOKIE['_fbp'] ) ) {
            $fbp = sanitize_text_field( wp_unslash( $_COOKIE['_fbp'] ) );
        }

        return $fbp;
    }

    /**
     * Retrieves the Facebook Click ID (FBC).
     *
     * Resolution order:
     * 1. ParamBuilder server-side extraction
     * 2. _fbc cookie (if fbclid hasn't changed)
     * 3. Constructed from fbclid query parameter
     * 4. Session fallback
     *
     * @return string|null The FBC value, or null if unavailable.
     */
    public static function get_fbc() {
        $fbc = FacebookParamBuilder::get_fbc();

        if ( empty( $fbc ) ) {
            $cookie_fbc = ! empty( $_COOKIE['_fbc'] )
                ? sanitize_text_field( wp_unslash( $_COOKIE['_fbc'] ) )
                : null;

            $request_fbclid = isset( $_GET['fbclid'] ) // phpcs:ignore WordPress.Security.NonceVerification
                ? self::sanitize_fbclid(
                    wp_unslash( $_GET['fbclid'] ) // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                )
                : null;

            if ( $request_fbclid && ( empty( $cookie_fbc ) || self::has_fbclid_changed( $cookie_fbc, $request_fbclid ) ) ) {
                $cur_time = (int) ( microtime( true ) * 1000 );
                $fbc      = 'fb.1.' . $cur_time . '.' . $request_fbclid;
            }

            if ( empty( $fbc ) && ! empty( $cookie_fbc ) ) {
                $fbc = $cookie_fbc;
            }

            if ( empty( $fbc ) && isset( $_SESSION['_fbc'] ) ) {
                $fbc = sanitize_text_field( $_SESSION['_fbc'] );
            }
        }

        if ( ! empty( $fbc ) && ! FacebookSignalState::is_held() ) {
            $_SESSION['_fbc'] = $fbc;
        }

        return $fbc;
    }

    /**
     * Checks whether the fbclid in the current request differs from
     * the one stored in the existing _fbc cookie.
     *
     * @param string      $cookie_fbc The current _fbc cookie value
     *                                (format: fb.{subdomain}.{timestamp}.{fbclid}).
     * @param string|null $request_fbclid The fbclid from the current request,
     *                                    or null if not present.
     *
     * @return bool True if fbclid has changed, false otherwise.
     */
    private static function has_fbclid_changed( $cookie_fbc, $request_fbclid ) {
        if ( null === $request_fbclid ) {
            return false;
        }

        $parts = explode( '.', $cookie_fbc );
        if ( count( $parts ) < 4 ) {
            return true;
        }

        $cookie_fbclid = implode( '.', array_slice( $parts, 3 ) );
        return $cookie_fbclid !== $request_fbclid;
    }

    /**
     * Decodes and validates an fbclid value.
     *
     * Decodes URL encoding first, then validates against an
     * allowlist of safe characters. Returns null if the value
     * contains unexpected characters.
     *
     * @param string $raw_fbclid The raw fbclid from the query string.
     *
     * @return string|null The sanitized fbclid, or null if invalid.
     */
    private static function sanitize_fbclid( $raw_fbclid ) {
        $decoded = rawurldecode( $raw_fbclid );
        if ( preg_match( '/^[A-Za-z0-9_-]+$/', $decoded ) ) {
            return $decoded;
        }
        return null;
    }

    /**
     * Validates an IP address.
     *
     * This function takes an IP address and returns true if it is valid,
     * false otherwise. The function uses the filter_var function to validate
     * the IP address, and it filters out private and reserved IP addresses.
     *
     * @param string $ip_address The IP address to validate.
     * @return bool True if the IP address is valid, false otherwise.
     */
    private static function is_valid_ip_address( $ip_address ) {
        return filter_var(
            $ip_address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 |
            FILTER_FLAG_IPV6 |
            FILTER_FLAG_NO_PRIV_RANGE |
                FILTER_FLAG_NO_RES_RANGE
        );
    }
}
