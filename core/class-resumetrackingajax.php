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

        if ( ! empty( $body['fbclid'] ) && empty( $_GET['fbclid'] ) ) {
            $_GET['fbclid'] = sanitize_text_field( $body['fbclid'] );
        }

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

        if ( ! empty( $events_to_send ) ) {
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
}
