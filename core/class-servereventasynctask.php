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

use FacebookPixelPlugin\Core\FacebookServerSideEvent;

use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\UserData;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\CustomData;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Content;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class ServerEventAsyncTask
 */
class ServerEventAsyncTask extends \WP_Async_Task {
    /**
     * The action to be performed by this task.
     *
     * @var string
     */
    protected $action = 'send_server_events';

    /**
     * Converts the normalized user data to the keys used in UserData.
     *
     * Normalized user data is an array of key => value pairs
     * where the key is a normalized version of the user data field name
     * and the value is the value of the field.
     *
     * This function takes that array and converts it to
     * the format used by UserData.
     *
     * @param array $user_data_normalized The normalized user data.
     *
     * @return array The converted user data.
     */
    private function convert_user_data( $user_data_normalized ) {
        return ServerEventFactory::map_user_data_keys( $user_data_normalized );
    }

    /**
     * Converts an array representation of an event into an Event object.
     *
     * This function takes an array that represents an event and
     * converts it into
     * an instance of the `Event` class. If the array contains `user_data`, a
     * `UserData` object is created and associated with the event. Similarly, if
     * `custom_data` is present, a `CustomData` object is created and
     * associated.
     * This includes handling nested `contents` and additional custom properties
     * like `fb_integration_tracking`.
     *
     * @param array $event_as_array The array representing the event.
     * @return Event The constructed Event object.
     */
    private function convert_array_to_event( $event_as_array ) {
        return ServerEventFactory::create_from_array( $event_as_array );
    }

    /**
     * Prepares event data for processing.
     *
     * This function takes an input array of events, normalizes each event,
     * and encodes the data into a base64 JSON string. If the input data
     * contains only a single event, it is wrapped in an array for consistency.
     *
     * @param array $data The input data containing events and the number
     *                    of events. The format is expected to be an array
     *                    where the first element is the event(s) and the
     *                    second element is the number of events.
     *
     * @return array An associative array containing 'event_data', a base64
     *               encoded JSON string of the events, and 'num_events',
     *               the number of events processed.
     *
     * @throws \Exception If there was an preprocessing error.
     */
    protected function prepare_data( $data ) {
        try {
            if ( ! empty( $data ) ) {
            $num_events = $data[1];
            $events     = $data[0];
            if ( 1 === $num_events ) {
                $events = array( $events );
            }
            $events_as_array = array();
            foreach ( $events as $event ) {
                $events_as_array[] = $event->normalize();
            }
            return array(
                'event_data' =>
                base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                    wp_json_encode( $events_as_array )
                ),
                'num_events' => $data[1],
            );
            }
        } catch ( \Exception $ex ) {
            throw $ex;
        }

        return array();
    }

    /**
     * Process the events sent via the AJAX action.
     *
     * This function decodes the JSON string sent in the $_POST['event_data']
     * and processes the events as an array of Event objects.
     *
     * @see FacebookServerSideEvent::send()
     *
     * @throws \Exception If there was an preprocessing error.
     */
    protected function run_action() {
        $num_events = isset( $_POST['num_events'] ) ? // phpcs:ignore WordPress.Security.NonceVerification.Missing
        sanitize_text_field(
            wp_unslash( $_POST['num_events'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
        ) : null;
        if ( 0 === $num_events ) {
            return;
        }
        $events_as_array = json_decode(
            base64_decode( // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                isset( $_POST['event_data'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                ? $_POST['event_data'] : null // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            ),
            true
        );
        if ( ! $events_as_array ) {
            return;
        }
        $events = array();
        foreach ( $events_as_array as $event_as_array ) {
            $event    = $this->convert_array_to_event( $event_as_array );
            $events[] = $event;
        }
        FacebookServerSideEvent::send( $events );
    }
}
