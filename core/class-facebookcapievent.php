<?php
/**
 * Facebook Pixel Plugin FacebookCapiEvent class.
 *
 * This file contains the main logic for FacebookCapiEvent.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookCapiEvent class.
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

use FacebookAds\Object\ServerSide\EventRequest;
use FacebookAds\ApiConfig;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class FacebookCapiEvent
 */
class FacebookCapiEvent {
    const REQUIRED_EVENT_DATA = array(
        'event_name',
        'event_time',
        'user_data',
        'action_source',
        'event_source_url',
    );

    const VALID_EVENT_ATTRIBUTES_TYPE = array(
        'event_name'                      => 'string',
        'event_time'                      => 'integer',
        'user_data'                       => 'object',
        'custom_data'                     => 'object',
        'event_source_url'                => 'string',
        'opt_out'                         => 'boolean',
        'event_id'                        => 'string',
        'action_source'                   => 'string',
        'data_processing'                 => 'string',
        'data_processing_options'         => 'array',
        'data_processing_options_country' => 'integer',
        'data_processing_options_state'   => 'integer',
        'app_data'                        => 'object',
        'extinfo'                         => 'object',
        'referrer_url'                    => 'string',
    );

    const VALID_CUSTOM_DATA = array(
        'value',
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
        'custom_properties',
    );

    /**
     * Hook into WordPress's AJAX actions to handle sending a CAPI event.
     */
    public function __construct() {
        add_action(
            'wp_ajax_send_capi_event',
            array( $this, 'send_capi_event' )
        );
    }

    /**
     * Retrieves the event custom data if available.
     *
     * @param array $custom_data The custom data to retrieve.
     * @return array The custom data array if not empty,
     * otherwise an empty array.
     */
    public static function get_event_custom_data( $custom_data ) {
        if ( empty( $custom_data ) ) {
            return array();
        } else {
            return $custom_data;
        }
    }

    /**
     * Sends a CAPI event.
     *
     * This function is responsible for sending a CAPI
     * event to Facebook's servers. It expects the event name
     * and custom data to be provided in the $_POST superglobal and
     * will validate the custom data before sending the event.
     * If the custom data is invalid, it will return an error message.
     *
     * If the event is successfully sent, it will return the
     * response from Facebook's servers.
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

        $api_version  = ApiConfig::APIVersion;
        $pixel_id     = FacebookWordpressOptions::get_pixel_id();
        $access_token = FacebookWordpressOptions::get_access_token();

        $url = 'https://graph.facebook.com/v' .
        $api_version . '/' . $pixel_id .
        '/events?access_token=' . $access_token;

        $event_name = isset( $_POST['event_name'] ) ?
        sanitize_text_field( wp_unslash( $_POST['event_name'] ) ) : null;

        if ( empty( $_POST['payload'] ) && ! empty( $event_name ) ) {
            $custom_data         = isset( $_POST['custom_data'] ) ?
            $_POST['custom_data'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            $invalid_custom_data =
            self::get_invalid_event_custom_data( $custom_data );
        if ( ! empty( $invalid_custom_data ) ) {
            $invalid_custom_data_msg = implode( ',', $invalid_custom_data );
            wp_send_json_error(
                wp_json_encode(
                    array(
                        'error' => array(
                            'message'        => 'Invalid custom_data attribute',
                            'error_user_msg' =>
                            'Invalid custom_data attributes: '
                            . $invalid_custom_data_msg,

                        ),
                    )
                )
            );
            wp_die();
        } else {
            $event = ServerEventFactory::safe_create_event(
                $event_name,
                array( $this, 'get_event_custom_data' ),
                array( $custom_data ),
                'fb-capi-event',
                true
            );

            $events = array();
            array_push( $events, $event );

            $event_request = ( new EventRequest( $pixel_id ) )
                ->setEvents( $events )
                ->setTestEventCode(
                    isset( $_POST['test_event_code'] ) ?
                    sanitize_text_field(
                        wp_unslash( $_POST['test_event_code'] )
                    ) :
                    null
                );

            $normalized_event = $event_request->normalize();

            if ( ! empty( $_POST['user_data'] ) ) {
            foreach ( $normalized_event['data'] as $key => $value ) {
                $normalized_event['data'][ $key ]['user_data'] +=
                isset( $_POST['user_data'] ) ?
                $_POST['user_data'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            }
            }

            $payload = wp_json_encode( $normalized_event );
        }
        } else {
            $validated_payload =
            self::validate_payload(
                isset( $_POST['payload'] ) ?
                $_POST['payload'] : null // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            );
        if ( ! $validated_payload['valid'] ) {
            wp_send_json_error(
                wp_json_encode(
                    array(
                        'error' => array(
                            'message'        => $validated_payload['message'],
                            'error_user_msg' =>
                            $validated_payload['error_user_msg'],
                        ),
                    )
                )
            );
            wp_die();
        } else {
            $payload = wp_json_encode(
                isset( $_POST['payload'] )
                ? $_POST['payload'] : null // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            );
        }
        }

        $args = array(
            'body'    => $payload,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => '*/*',
            ),
            'method'  => 'POST',
        );

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        } else {
            wp_send_json_success( wp_remote_retrieve_body( $response ) );
        }
        wp_die();
    }

    /**
     * Given a custom data array, returns an array of
     * the custom data keys which are not valid
     *
     * @param array $custom_data The custom data array to check.
     * @return array An array of the invalid custom data keys.
     */
    public function get_invalid_event_custom_data( $custom_data ) {
        if ( empty( $custom_data ) ) {
            return array();
        }

        $invalid_custom_data = array();
        foreach ( $custom_data as $key => $value ) {
          if ( ! in_array( $key, self::VALID_CUSTOM_DATA, true ) ) {
              array_push( $invalid_custom_data, $key );
          }
        }
        return $invalid_custom_data;
    }

    /**
     * Validates the given payload to ensure it meets the required criteria.
     *
     * This function checks if the payload is non-empty and contains valid JSON.
     * It further verifies that each event within the payload's data includes
     * all required attributes, with appropriate data types, and that
     * custom data attributes are valid.
     *
     * @param array $payload The payload to validate.
     * @return array An associative array containing a 'valid' key indicating
     *               the validity of the payload, and 'message' and
     *               'error_user_msg' keys providing error details if invalid.
     */
    public function validate_payload( $payload ) {
        $response = array(
            'valid' => true,
        );
        if ( empty( $payload ) ) {
            $response['valid']          = false;
            $response['message']        = 'Empty payload';
            $response['error_user_msg'] = 'Payload is empty.';
        } elseif ( ! self::validate_json( $payload ) ) {
            $response['valid']          = false;
            $response['message']        = 'Invalid JSON in payload';
            $response['error_user_msg'] = 'Invalid JSON in payload.';
        } else {
            foreach ( $payload['data'] as $event ) {
            foreach ( self::REQUIRED_EVENT_DATA as $attribute ) {
                if ( ! array_key_exists( $attribute, $event ) ) {
                  if ( ! empty( $response['message'] ) ) {
                      $response['error_user_msg'] .=
                      ", {$attribute} attribute is missing";
                  } else {
                      $response['valid']          = false;
                      $response['message']        = 'Missing required attribute';
                      $response['error_user_msg'] =
                      "{$attribute} attribute is missing";
                  }
                }
            }

            if ( $response['valid'] ) {
                $invalid_attributes = self::validate_event_attributes_type(
                    $event
                );
                if ( ! empty( $invalid_attributes ) ) {
                    $invalid_attributes_msg     = implode(
                        ',',
                        $invalid_attributes
                    );
                    $response['valid']          = false;
                    $response['message']        = 'Invalid attribute type';
                    $response['error_user_msg'] =
                    "Invalid attribute type: {$invalid_attributes_msg}";
                } elseif ( isset( $event['custom_data'] ) ) {
                    $invalid_custom_data =
                    self::get_invalid_event_custom_data(
                        $event['custom_data']
                    );
                if ( ! empty( $invalid_custom_data ) ) {
                    $invalid_custom_data_msg    =
                    implode( ',', $invalid_custom_data );
                    $response['valid']          = false;
                    $response['message']        =
                    'Invalid custom_data attribute';
                    $response['error_user_msg'] =
                    'Invalid custom_data attributes: '
                    . $invalid_custom_data_msg;
                }
                }
            }
            }
        }

        return $response;
    }

    /**
     * Validates a payload as JSON.
     *
     * @param array $payload The payload to validate.
     * @return bool Whether the payload is valid JSON.
     */
    public function validate_json( $payload ) {
        $json_string = wp_json_encode( $payload );
        $regex       = '/^(?:\{.*\}|\[.*\])$/s';

        return preg_match( $regex, $json_string );
    }

    /**
     * Validate the type of attributes in an event.
     *
     * @param array $event An event with its attributes.
     *
     * @return array An array of invalid attributes.
     */
    public function validate_event_attributes_type( $event ) {
        if ( is_numeric( $event['event_time'] ) ) {
            $event['event_time'] = (int) $event['event_time'];
        }
        $invalid_attributes = array();
        $event              = json_decode( wp_json_encode( $event ) );
        foreach ( $event as $key => $value ) {
        if ( 'integer' === self::VALID_EVENT_ATTRIBUTES_TYPE[ $key ] ) {
            if ( ! is_numeric( $value ) ) {
            array_push( $invalid_attributes, $key );
            }
        } elseif (
            gettype( $value ) !== self::VALID_EVENT_ATTRIBUTES_TYPE[ $key ]
            ) {
                array_push( $invalid_attributes, $key );
        }
        }
        return $invalid_attributes;
    }
}
