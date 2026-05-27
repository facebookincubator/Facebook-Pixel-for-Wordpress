<?php
/**
 * Facebook Pixel Plugin FacebookCapiCircuitBreaker class.
 *
 * Half-open circuit breaker for Conversions API calls.
 * Detects OAuth/permission errors, blocks calls while the token
 * is known-bad, and periodically retries to self-heal.
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

use FacebookPixelPlugin\FacebookAds\Http\Exception\AuthorizationException;
use FacebookPixelPlugin\FacebookAds\Http\Exception\PermissionException;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class FacebookCapiCircuitBreaker
 */
class FacebookCapiCircuitBreaker {

    /**
     * Checks whether sending is allowed right now.
     *
     * Returns true when the circuit is closed (no error) or
     * half-open (retry interval elapsed). Returns false when
     * the circuit is open (recent auth error).
     *
     * @return bool
     */
    public static function is_send_allowed() {
        $timestamp = get_transient(
            FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT
        );
        if ( false === $timestamp ) {
            return true;
        }
        $elapsed = time() - (int) $timestamp;
        return $elapsed >= FacebookPluginConfig::CONNECTION_RETRY_INTERVAL;
    }

    /**
     * Whether the circuit breaker is currently tripped (open or half-open).
     *
     * @return bool
     */
    public static function is_tripped() {
        return false !== get_transient(
            FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT
        );
    }

    /**
     * Records a successful API call. Clears the transient if the
     * circuit was in half-open state (silent self-heal).
     *
     * @return void
     */
    public static function record_success() {
        if ( self::is_tripped() ) {
            delete_transient(
                FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT
            );
        }
    }

    /**
     * Inspects an exception and trips the circuit breaker if it
     * indicates an auth or permission error.
     *
     * Subcode 452 (session mismatch) is exempt because it may
     * self-resolve without merchant action.
     *
     * @param \Exception $e The caught exception.
     * @return void
     */
    public static function record_exception( \Exception $e ) {
        if ( $e instanceof AuthorizationException ) {
            if ( 452 === $e->getErrorSubcode() ) {
                return;
            }
            self::trip();
        } elseif ( $e instanceof PermissionException ) {
            self::trip();
        }
    }

    /**
     * Trips the circuit breaker by setting the transient.
     *
     * @return void
     */
    private static function trip() {
        set_transient(
            FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT,
            time(),
            DAY_IN_SECONDS
        );
    }
}
