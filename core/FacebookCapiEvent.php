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
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
			wp_die();
		}

		$api_version  = ApiConfig::APIVersion;
		$pixel_id     = FacebookWordpressOptions::getPixelId();
		$access_token = FacebookWordpressOptions::getAccessToken();

		$url = "https://graph.facebook.com/v{$api_version}/{$pixel_id}/events?access_token={$access_token}";

		$event_name = $_POST['event_name'];

		if ( empty( $_POST['payload'] ) ) {
			$custom_data = $_POST['custom_data'];
			$event       = ServerEventFactory::safeCreateEvent(
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
		} else {
			$invalid_custom_data = self::get_invalid_event_custom_data( $_POST['payload'] );
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

	public function get_invalid_event_custom_data( $payload ) {
		$invalid_custom_data = array();
		foreach ( $payload['data'] as $event ) {
			$custom_data = $event['custom_data'];
			foreach ( $custom_data as $key => $value ) {
				if ( ! in_array( $key, self::VALID_CUSTOM_DATA, true ) ) {
					array_push( $invalid_custom_data, $key );
				}
			}
		}
		return $invalid_custom_data;
	}
}
