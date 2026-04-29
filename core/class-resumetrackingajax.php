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

use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Content;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\CustomData;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\UserData;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * AJAX endpoint for replaying queued events after signals grant.
 */
class ResumeTrackingAjax {
    const ACTION        = 'fbpix_resume_tracking';
    const NONCE_ACTION  = 'fbpix_resume_tracking';
    const MAX_EVENTS    = 20;
    const MAX_EVENT_AGE = 1800;

    /**
     * Default rate-limit window in seconds (5 minutes).
     *
     * @var int
     */
    const RATE_LIMIT_WINDOW = 300;

    /**
     * Default maximum accepted events per IP per window.
     *
     * @var int
     */
    const RATE_LIMIT_MAX_EVENTS = 80;

    /**
     * Object-cache group used by atomic rate-limit counters.
     *
     * @var string
     */
    const RATE_LIMIT_GROUP = 'fbpix_resume_tracking_rl';

    /**
     * Allowed replayed event names.
     *
     * @var string[]
     */
    private static $allowed_events = array(
        'PageView',
        'ViewContent',
        'Search',
        'AddToCart',
        'AddToWishlist',
        'InitiateCheckout',
        'AddPaymentInfo',
        'Purchase',
        'Lead',
        'CompleteRegistration',
        'Contact',
        'CustomizeProduct',
        'Donate',
        'FindLocation',
        'Schedule',
        'StartTrial',
        'SubmitApplication',
        'Subscribe',
    );

    /**
     * Register AJAX handlers.
     */
    public function __construct() {
        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
        add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle' ) );
    }

    /**
     * Process queued event replay.
     *
     * @return void
     */
    public function handle() {
        $body = json_decode( file_get_contents( 'php://input' ), true );

        if ( ! is_array( $body ) ) {
            wp_send_json_error( array( 'message' => 'Invalid request body.' ), 400 );
        }

        $nonce = isset( $body['security'] ) ?
            sanitize_text_field( $body['security'] ) : '';
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
        }

        if ( $this->is_rate_limited() ) {
            wp_send_json_error( array( 'message' => 'Rate limit exceeded.' ), 429 );
        }

        $fbclid = ! empty( $body['fbclid'] ) ?
            sanitize_text_field( $body['fbclid'] ) : '';
        $fbp    = ! empty( $body['fbp'] ) ?
            sanitize_text_field( $body['fbp'] ) : '';
        $fbc    = ! empty( $body['fbc'] ) ?
            sanitize_text_field( $body['fbc'] ) : '';

        // Expose queued attribution to ServerEventFactory so resumed events
        // share the same attribution path as normal CAPI events.
        $restore_get_fbclid = $this->temporarily_set_superglobal_value( '_GET', 'fbclid', $fbclid );
        $restore_cookie_fbp = $this->temporarily_set_superglobal_value( '_COOKIE', '_fbp', $fbp );
        $restore_cookie_fbc = $this->temporarily_set_superglobal_value( '_COOKIE', '_fbc', $fbc );

        $events = isset( $body['events'] ) && is_array( $body['events'] ) ?
            array_slice( $body['events'], 0, self::MAX_EVENTS ) :
            array();
        $now    = time();

        $events_to_send = array();
        foreach ( $events as $event_data ) {
            if ( ! $this->validate_event( $event_data, $now ) ) {
                continue;
            }

            $events_to_send[] = $this->build_event( $event_data );
        }

        $this->restore_superglobal_value( '_GET', 'fbclid', $restore_get_fbclid );
        $this->restore_superglobal_value( '_COOKIE', '_fbp', $restore_cookie_fbp );
        $this->restore_superglobal_value( '_COOKIE', '_fbc', $restore_cookie_fbc );

        if ( ! empty( $events_to_send ) ) {
            $this->record_rate_limit_usage( count( $events_to_send ) );
            FacebookServerSideEvent::send( $events_to_send );
        }

        wp_send_json_success(
            array(
                'fbp'        => ServerEventFactory::get_fbp_value(),
                'fbc'        => ServerEventFactory::get_fbc_value(),
                'sent_count' => count( $events_to_send ),
            )
        );
    }

    /**
     * Validate incoming queued event.
     *
     * @param mixed $event_data Event payload.
     * @param int   $now        Current timestamp.
     *
     * @return bool
     */
    private function validate_event( $event_data, $now ) {
        if ( ! is_array( $event_data ) || empty( $event_data['event_name'] ) ) {
            return false;
        }

        $event_name = sanitize_text_field( $event_data['event_name'] );
        if ( ! in_array( $event_name, self::$allowed_events, true )
            && ! preg_match( '/^[A-Za-z][A-Za-z0-9_]{0,49}$/', $event_name ) ) {
            return false;
        }

        if ( ! empty( $event_data['event_time'] ) ) {
            $event_time = absint( $event_data['event_time'] );
            if ( $event_time > $now || ( $now - $event_time ) > self::MAX_EVENT_AGE ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build replay event from queue payload.
     *
     * @param array $event_data Event payload.
     *
     * @return Event
     */
    private function build_event( $event_data ) {
        $event_name = sanitize_text_field( $event_data['event_name'] );
        $event      = ServerEventFactory::new_event( $event_name );

        if ( ! empty( $event_data['event_time'] ) ) {
            $event->setEventTime( absint( $event_data['event_time'] ) );
        }

        if ( ! empty( $event_data['event_id'] ) ) {
            $event->setEventId(
                sanitize_text_field( $event_data['event_id'] )
            );
        }

        if ( ! empty( $event_data['event_source_url'] ) ) {
            $event->setEventSourceUrl(
                esc_url_raw( $event_data['event_source_url'] )
            );
        }

        $custom_data = isset( $event_data['custom_data'] ) &&
            is_array( $event_data['custom_data'] ) ?
            $this->build_custom_data( $event_data['custom_data'] ) :
            new CustomData();

        $event->setCustomData( $custom_data );
        $event->setActionSource( 'website' );

        if ( ! empty( $event_data['user_data'] ) && is_array( $event_data['user_data'] ) ) {
            $forwarded = $this->build_user_data_from_payload( $event_data['user_data'] );
            if ( null !== $forwarded ) {
                $existing = $event->getUserData();
                if ( null !== $existing ) {
                    $forwarded->setFbp( $existing->getFbp() );
                    $forwarded->setFbc( $existing->getFbc() );
                    $forwarded->setClientIpAddress(
                        $existing->getClientIpAddress()
                    );
                    $forwarded->setClientUserAgent(
                        $existing->getClientUserAgent()
                    );
                }
                $event->setUserData( $forwarded );
            }
        }

        return $event;
    }

    /**
     * Build custom data object from queue payload.
     *
     * @param array $custom_data Event custom data.
     *
     * @return CustomData
     */
    private function build_custom_data( $custom_data ) {
        $sanitized  = array();
        $extra_data = array();
        $valid_keys = array(
            'value',
            'net_revenue',
            'currency',
            'content_name',
            'content_category',
            'content_ids',
            'contents',
            'content_type',
            'order_id',
            'predicted_ltv',
            'num_items',
            'status',
            'search_string',
            'item_number',
            'delivery_category',
        );

        foreach ( $custom_data as $key => $value ) {
            $key = sanitize_text_field( $key );
            if ( 'contents' === $key && is_array( $value ) ) {
                $sanitized[ $key ] = $value;
                continue;
            }

            if ( is_array( $value ) ) {
                $value = array_map(
                    array( $this, 'sanitize_scalar_or_array_value' ),
                    $value
                );
            } else {
                $value = $this->sanitize_scalar_or_array_value( $value );
            }

            if ( in_array( $key, $valid_keys, true ) ) {
                $sanitized[ $key ] = $value;
            } else {
                $extra_data[ $key ] = $value;
            }
        }

        $event_custom_data = new CustomData( $sanitized );

        if ( isset( $sanitized['contents'] ) ) {
            $contents = array();
            foreach ( $sanitized['contents'] as $content_as_array ) {
                if ( ! is_array( $content_as_array ) ) {
                    continue;
                }
                if ( isset( $content_as_array['id'] ) &&
                    ! isset( $content_as_array['product_id'] ) ) {
                    $content_as_array['product_id'] = $content_as_array['id'];
                }
                $contents[] = new Content( $content_as_array );
            }
            $event_custom_data->setContents( $contents );
        }

        foreach ( $extra_data as $key => $value ) {
            $event_custom_data->addCustomProperty( $key, $value );
        }

        return $event_custom_data;
    }

    /**
     * Build a UserData object from a forwarded resume payload.
     *
     * Accepts the normalized short-key shape produced by UserData::normalize()
     * (em, ph, ln, fn, ct, st, zp, country, external_id, etc.). Hashable PII
     * keys are expected to already be SHA256 hashes; the SDK's normalize() at
     * send time skips hashing for already-hashed values.
     *
     * @param array $user_data Forwarded user_data payload.
     *
     * @return UserData|null
     */
    private function build_user_data_from_payload( array $user_data ) {
        $array_setters = array(
            'em'          => 'setEmails',
            'ph'          => 'setPhones',
            'ge'          => 'setGenders',
            'db'          => 'setDatesOfBirth',
            'ln'          => 'setLastNames',
            'fn'          => 'setFirstNames',
            'ct'          => 'setCities',
            'st'          => 'setStates',
            'zp'          => 'setZipCodes',
            'country'     => 'setCountryCodes',
            'external_id' => 'setExternalIds',
        );

        $scalar_setters = array(
            'subscription_id' => 'setSubscriptionId',
            'fb_login_id'     => 'setFbLoginId',
            'lead_id'         => 'setLeadId',
            'f5first'         => 'setF5first',
            'f5last'          => 'setF5last',
            'fi'              => 'setFi',
            'dobd'            => 'setDobd',
            'dobm'            => 'setDobm',
            'doby'            => 'setDoby',
            'madid'           => 'setMadid',
            'anon_id'         => 'setAnonId',
            'ctwa_clid'       => 'setCtwaClid',
            'page_id'         => 'setPageId',
        );

        $ud      = new UserData();
        $applied = false;

        foreach ( $array_setters as $key => $setter ) {
            if ( empty( $user_data[ $key ] ) || ! is_array( $user_data[ $key ] ) ) {
                continue;
            }
            $values = array();
            foreach ( $user_data[ $key ] as $value ) {
                if ( is_array( $value ) || is_object( $value ) ) {
                    continue;
                }
                $clean = sanitize_text_field( (string) $value );
                if ( '' !== $clean ) {
                    $values[] = $clean;
                }
            }
            if ( ! empty( $values ) ) {
                $ud->{$setter}( $values );
                $applied = true;
            }
        }

        foreach ( $scalar_setters as $key => $setter ) {
            if ( ! isset( $user_data[ $key ] ) ) {
                continue;
            }
            if ( is_array( $user_data[ $key ] ) || is_object( $user_data[ $key ] ) ) {
                continue;
            }
            $clean = sanitize_text_field( (string) $user_data[ $key ] );
            if ( '' === $clean ) {
                continue;
            }
            $ud->{$setter}( $clean );
            $applied = true;
        }

        return $applied ? $ud : null;
    }

    /**
     * Sanitize scalar value recursively.
     *
     * @param mixed $value Value to sanitize.
     *
     * @return mixed
     */
    private function sanitize_scalar_or_array_value( $value ) {
        if ( is_array( $value ) ) {
            return array_map(
                array( $this, 'sanitize_scalar_or_array_value' ),
                $value
            );
        }

        if ( is_numeric( $value ) ) {
            return $value + 0;
        }

        return sanitize_text_field( (string) $value );
    }

    /**
     * Temporarily sets a superglobal value and returns data needed to restore it.
     *
     * @param string $superglobal Superglobal name without dollar sign.
     * @param string $key         Key to set.
     * @param string $value       Value to set; empty string is a no-op.
     *
     * @return array
     */
    private function temporarily_set_superglobal_value( $superglobal, $key, $value ) {
        $had_value = isset( $GLOBALS[ $superglobal ][ $key ] );
        $original  = $had_value ? $GLOBALS[ $superglobal ][ $key ] : null;

        if ( '' !== $value && null !== $value ) {
            $GLOBALS[ $superglobal ][ $key ] = $value;
        }

        return array(
            'had_value' => $had_value,
            'original'  => $original,
        );
    }

    /**
     * Restores a superglobal value saved by temporarily_set_superglobal_value().
     *
     * @param string $superglobal Superglobal name without dollar sign.
     * @param string $key         Key to restore.
     * @param array  $restore     Restore data.
     *
     * @return void
     */
    private function restore_superglobal_value( $superglobal, $key, $restore ) {
        if ( ! empty( $restore['had_value'] ) ) {
            $GLOBALS[ $superglobal ][ $key ] = $restore['original'];
        } else {
            unset( $GLOBALS[ $superglobal ][ $key ] );
        }
    }

    /**
     * Whether the current client is over its resume-tracking rate limit.
     *
     * Counts accepted events (not requests) per IP per window. Both the
     * window length and the cap are filterable.
     *
     * @return bool
     */
    private function is_rate_limited() {
        $max = (int) apply_filters(
            'fbpix_resume_tracking_rate_limit_max',
            self::RATE_LIMIT_MAX_EVENTS
        );

        if ( $max <= 0 ) {
            return false;
        }

        return $this->get_rate_limit_count() >= $max;
    }

    /**
     * Records that the current client just had N events accepted.
     *
     * Uses wp_cache_incr when a persistent object cache is available so the
     * counter increments atomically; falls back to a transient read+write for
     * single-process / no-object-cache installs.
     *
     * @param int $accepted_event_count Number of events accepted in this request.
     *
     * @return void
     */
    private function record_rate_limit_usage( $accepted_event_count ) {
        if ( $accepted_event_count <= 0 ) {
            return;
        }

        $window = (int) apply_filters(
            'fbpix_resume_tracking_rate_limit_window',
            self::RATE_LIMIT_WINDOW
        );
        if ( $window <= 0 ) {
            return;
        }

        $key   = $this->get_rate_limit_key();
        $delta = (int) $accepted_event_count;

        if ( $this->using_atomic_cache() ) {
            wp_cache_add( $key, 0, self::RATE_LIMIT_GROUP, $window );
            if ( false === wp_cache_incr( $key, $delta, self::RATE_LIMIT_GROUP ) ) {
                wp_cache_set( $key, $delta, self::RATE_LIMIT_GROUP, $window );
            }
            return;
        }

        $current = (int) get_transient( $key );
        set_transient( $key, $current + $delta, $window );
    }

    /**
     * Reads the current rate-limit counter for this client.
     *
     * @return int
     */
    private function get_rate_limit_count() {
        $key = $this->get_rate_limit_key();

        if ( $this->using_atomic_cache() ) {
            $count = wp_cache_get( $key, self::RATE_LIMIT_GROUP );
            if ( false !== $count ) {
                return (int) $count;
            }
        }

        return (int) get_transient( $key );
    }

    /**
     * Whether wp_cache_incr-backed atomic increments are available.
     *
     * @return bool
     */
    private function using_atomic_cache() {
        return function_exists( 'wp_cache_incr' )
            && function_exists( 'wp_using_ext_object_cache' )
            && wp_using_ext_object_cache();
    }

    /**
     * Builds a transient key scoped to the requesting client IP.
     *
     * @return string
     */
    private function get_rate_limit_key() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ?
            sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) :
            '';
        return 'fbpix_resume_tracking_rl_' . md5( $ip );
    }
}
