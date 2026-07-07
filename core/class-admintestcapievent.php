<?php
/**
 * Facebook Pixel Plugin AdminTestCapiEvent class.
 *
 * This file contains the main logic for AdminTestCapiEvent.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define AdminTestCapiEvent class.
 *
 * @return void
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

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class AdminTestCapiEvent
 *
 * Admin "Test Events" endpoint (wp_ajax_send_capi_event). It is a thin
 * controller: read the admin-supplied inputs, map them to server Event(s) via
 * EventFactory, send them through Signals, and return the result to the admin
 * panel. The event structure lives in EventFactory / the SDK objects, not here;
 * a malformed event surfaces as a non-success result from the Conversions API.
 */
class AdminTestCapiEvent {
    /**
     * Shared Signals service.
     *
     * @var Signals
     */
    private $signals;

    /**
     * Hook into WordPress's AJAX actions to handle sending a CAPI event.
     *
     * @param Signals $signals Shared signals service.
     */
    public function __construct( Signals $signals ) {
        $this->signals = $signals;
        add_action(
            'wp_ajax_send_capi_event',
            array( $this, 'send_capi_event' )
        );
    }

    /**
     * Handles the admin "Test Events" request.
     *
     * Reads the posted inputs, maps them to server Event(s) via EventFactory,
     * sends them through Signals, and returns the Conversions API result to the
     * admin panel (events_received on success, the error message otherwise).
     */
    public function send_capi_event() {
        $nonce = isset( $_POST['nonce'] ) ?
        sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : null;
        if ( ! isset( $nonce ) ||
        ! wp_verify_nonce( $nonce, 'send_capi_event_nonce' ) ) {
        wp_send_json_error(
            wp_json_encode(
                array(
                    'error' => array(
                        'message'        => 'Invalid nonce',
                        'error_user_msg' => 'Invalid nonce',
                    ),
                )
            )
        );
            wp_die();
        }

        $test_event_code = null;
        if ( isset( $_POST['test_event_code'] ) ) {
            $raw_test_event_code = sanitize_text_field(
                wp_unslash( $_POST['test_event_code'] )
            );
            if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $raw_test_event_code ) ) {
                wp_send_json_error(
                    wp_json_encode(
                        array(
                            'error' => array(
                                'message'        => 'Invalid test event code',
                                'error_user_msg' =>
                                'Test event code must contain only'
                                . ' letters, numbers, and underscores.',
                            ),
                        )
                    )
                );
                wp_die();
            }
            $test_event_code = $raw_test_event_code;
        }

        $event_name = isset( $_POST['event_name'] ) ?
        sanitize_text_field( wp_unslash( $_POST['event_name'] ) ) : null;

        if ( empty( $_POST['payload'] ) && ! empty( $event_name ) ) {
            $custom_data = ( isset( $_POST['custom_data'] )
                && is_array( $_POST['custom_data'] ) ) ?
                $_POST['custom_data'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            $events      = array(
                EventFactory::create(
                    $event_name,
                    $custom_data,
                    'fb-capi-event',
                    true
                ),
            );
        } else {
            $raw_payload = isset( $_POST['payload'] ) ?
                $_POST['payload'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            if ( isset( $raw_payload['test_event_code'] )
                && empty( $test_event_code ) ) {
                $test_event_code = sanitize_text_field(
                    $raw_payload['test_event_code']
                );
            }
            $events = array();
            if ( isset( $raw_payload['data'] ) ) {
                foreach ( $raw_payload['data'] as $event_as_array ) {
                    $events[] = EventFactory::create_from_array(
                        $event_as_array
                    );
                }
            }
        }

        $result = $this->signals->send( $events, $test_event_code );

        if ( $result && $result['success'] ) {
            wp_send_json_success(
                wp_json_encode(
                    array(
                        'events_received' => $result['events_received'],
                    )
                )
            );
        } else {
            $error_msg = isset( $result['error']['message'] )
                ? $result['error']['message'] : 'Unknown error';
            wp_send_json_success(
                wp_json_encode(
                    array(
                        'error' => array(
                            'message'        => $error_msg,
                            'error_user_msg' => $error_msg,
                        ),
                    )
                )
            );
        }
        wp_die();
    }
}
