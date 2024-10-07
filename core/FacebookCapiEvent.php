<?php
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

/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Core;

use FacebookAds\Object\ServerSide\EventRequest;
use FacebookAds\ApiConfig;

defined( 'ABSPATH' ) or die( 'Direct access not allowed' );

class FacebookCapiEvent {
	const REQUIRED_EVENT_DATA = array(
		'event_name',
		'event_time',
		'user_data',
		'action_source',
		'event_source_url',
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

	public function __construct() {
		add_action( 'wp_ajax_send_capi_event', array( $this, 'send_capi_event' ) );
	}

	public static function get_event_custom_data( $custom_data ) {
		if ( empty( $custom_data ) ) {
			return array();
		} else {
			return $custom_data;
		}
	}

	public function send_capi_event() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'send_capi_event_nonce' ) ) {
			wp_send_json_error(
				json_encode(
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
		$pixel_id     = FacebookWordpressOptions::getPixelId();
		$access_token = FacebookWordpressOptions::getAccessToken();

		$url = "https://graph.facebook.com/v{$api_version}/{$pixel_id}/events?access_token={$access_token}";

		$event_name = $_POST['event_name'];

		if ( empty( $_POST['payload'] ) ) {
			$custom_data = $_POST['custom_data'];
			$invalid_custom_data = self::get_invalid_event_custom_data( $custom_data );
			if ( ! empty( $invalid_custom_data ) ) {
				$invalid_custom_data_msg = implode( ',', $invalid_custom_data );
				wp_send_json_error(
					json_encode(
						array(
							'error' => array(
								'message'        => 'Invalid custom_data attribute',
								'error_user_msg' => "Invalid custom_data attributes: {$invalid_custom_data_msg}",
							),
						)
					)
				);
				wp_die();
			} else {
				$event = ServerEventFactory::safeCreateEvent(
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
					->setTestEventCode( $_POST['test_event_code'] );

				$payload = json_encode( $event_request->normalize() );
			}
		} else {
			$validated_payload = self::validate_payload( $_POST['payload'] );
			if ( ! $validated_payload['valid'] ) {
				wp_send_json_error(
					json_encode(
						array(
							'error' => array(
								'message'        => $validated_payload['message'],
								'error_user_msg' => $validated_payload['error_user_msg'],
							),
						)
					)
				);
				wp_die();
			} else {
				$payload = json_encode( $_POST['payload'] );
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

	public function get_invalid_event_custom_data( $custom_data ) {
		$invalid_custom_data = array();
		foreach ( $custom_data as $key => $value ) {
			if ( ! in_array( $key, self::VALID_CUSTOM_DATA, true ) ) {
				array_push( $invalid_custom_data, $key );
			}
		}
		return $invalid_custom_data;
	}

	public function validate_payload( $payload ) {
		$response = array(
			'valid' => true
		);
		if ( empty( $payload ) ) {
			$response['valid']          = false;
			$response['message']        = 'Empty payload';
			$response['error_user_msg'] = 'Payload is empty.';
		} else if ( ! self::validate_json( $payload ) ) {
			$response['valid']          = false;
			$response['message']        = 'Invalid JSON in payload';
			$response['error_user_msg'] = 'Invalid JSON in payload.';
		} else {
			foreach ( $payload['data'] as $event ) {
				foreach ( self::REQUIRED_EVENT_DATA as $attribute ) {
					if ( ! array_key_exists( $attribute, $event ) ) {
						if ( ! empty( $response['message'] ) ) {
							$response['error_user_msg'] .= ", {$attribute} attribute is missing";
						} else {
							$response['valid']          = false;
							$response['message']        = 'Missing required attribute';
							$response['error_user_msg'] = "{$attribute} attribute is missing";
						}
					}
				}

				if ( $response['valid'] && isset( $event['custom_data'] ) ) {
					$invalid_custom_data = self::get_invalid_event_custom_data( $event['custom_data'] );
					if ( ! empty( $invalid_custom_data ) ) {
						$invalid_custom_data_msg    = implode( ',', $invalid_custom_data );
						$response['valid']          = false;
						$response['message']        = 'Invalid custom_data attribute';
						$response['error_user_msg'] = "Invalid custom_data attributes: {$invalid_custom_data_msg}";
					} else {
						$invalid_attributes = self::validate_event_attributes_type( $event );
						if ( ! empty( $invalid_attributes ) ) {
							$invalid_attributes_msg     = implode( ',', $invalid_attributes );
							$response['valid']          = false;
							$response['message']        = 'Invalid attribute type';
							$response['error_user_msg'] = "Invalid attribute type: {$invalid_attributes_msg}";
						}
					}
				}
			}
		}
		
		return $response;
	}

	public function validate_json( $payload ) {
		json_encode( $payload );
    
		if ( json_last_error() === JSON_ERROR_NONE ) {
			return true;
		} else {
			return false;
		}
	}

	public function validate_event_attributes_type( $event ) {
		if ( is_numeric( $event['event_time'] ) ) {
			$event['event_time'] = (int) $event['event_time'];
		}
		$invalid_attributes = array();
		$event              = json_decode( json_encode( $event ) );
		foreach (self::REQUIRED_EVENT_DATA as $key => $value) {
			if ( $value == 'integer' ) {
				if ( ! is_numeric( $event->{$key} ) ) {
					array_push( $invalid_attributes, $key );
				}
			} else {
				if ( gettype( $event->{$key} ) != $value ) {
					array_push( $invalid_attributes, $key );
				}
			}
		}
		return $invalid_attributes;
	}
}
