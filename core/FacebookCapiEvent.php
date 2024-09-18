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
	const EVENTS_WITH_CUSTOM_DATA = array(
		'Purchase',
		'AddToCart',
		'InitiateCheckout',
		'ViewContent',
		'Search',
		'AddPaymentInfo',
		'AddToWishlist',
	);

	const EVENT_CUSTOM_DATA_EXAMPLE = array(
		'currency'     => 'USD',
		'value'        => 123.321,
		'content_type' => 'product',
		'content_ids'  => array( 123, 321 ),
	);

	public function __construct() {
		add_action( 'wp_ajax_send_capi_test_event', array( $this, 'send_capi_test_event' ) );
	}

	public static function get_event_data( $custom_data_required ) {
		if ( $custom_data_required ) {
			return self::EVENT_CUSTOM_DATA_EXAMPLE;
		} else {
			return array();
		}
	}

	public function send_capi_test_event() {
		$api_version  = ApiConfig::APIVersion;
		$pixel_id     = FacebookWordpressOptions::getPixelId();
		$access_token = FacebookWordpressOptions::getAccessToken();

		$url = "https://graph.facebook.com/v{$api_version}/{$pixel_id}/events?access_token={$access_token}";

		$event_name = $_POST['event_name'];

		if ( empty( $_POST['payload'] ) ) {
			$custom_data_required = in_array( $event_name, self::EVENTS_WITH_CUSTOM_DATA, true );
			$event                = ServerEventFactory::safeCreateEvent(
				$event_name,
				array( $this, 'get_event_data' ),
				array( $custom_data_required ),
				'wp-fb-capi-test',
				true
			);

			$events = array();
			array_push( $events, $event );

			$event_request = ( new EventRequest( $pixel_id ) )
				->setEvents( $events )
				->setTestEventCode( $_POST['test_event_code'] );

			$payload = json_encode( $event_request->normalize() );
		} else {
			$payload = json_encode( $_POST['payload'] );
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
}
