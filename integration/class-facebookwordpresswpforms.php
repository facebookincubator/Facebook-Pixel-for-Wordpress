<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWPForms class.
 *
 * This file contains the main logic for FacebookWordpressWPForms.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressWPForms class.
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

namespace FacebookPixelPlugin\Integration;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

use FacebookPixelPlugin\Core\FacebookPixel;
use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\FacebookWordPressOptions;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\UserData;

/**
 * FacebookWordpressWPForms class.
 */
class FacebookWordpressWPForms extends FacebookWordpressIntegrationBase {
	const PLUGIN_FILE   = 'wpforms-lite/wpforms.php';
	const TRACKING_NAME = 'wpforms-lite';

	/**
	 * Hooks into WPForms to inject the Pixel code.
	 *
	 * This method adds an action to the 'wpforms_process_before' hook,
	 * which will trigger the 'trackEvent' method. It ensures that
	 * the Pixel code is injected during the form processing stage.
	 */
	public static function inject_pixel_code() {
		add_action(
			'wpforms_process_before',
			array( __CLASS__, 'trackEvent' ),
			20,
			2
		);

        add_action(
			'wpforms_frontend_confirmation_message',
			array( __CLASS__, 'injectLeadEvent' ),
			30,
            4
		);
	}

	/**
	 * Tracks a server-side event for a form submission in WPForms.
	 *
	 * This method is hooked into the 'wpforms_process_before' action, which is
	 * fired by WPForms before a form is processed.
     * It then calls the track method
	 * on the FacebookServerSideEvent instance, which generates a lead event for
	 * the form submission.
	 *
	 * If the user is an internal user, the method returns without tracking
	 * any event.
	 *
	 * @param array $entry The form entry data.
	 * @param array $form_data The form data.
	 *
	 * @return void
	 */
	public static function trackEvent( $entry, $form_data ) {
		if ( FacebookPluginUtils::is_internal_user() ) {
            return;
		}

		$server_event = ServerEventFactory::safe_create_event(
			'Lead',
			array( __CLASS__, 'readFormData' ),
			array( $entry, $form_data ),
			self::TRACKING_NAME,
			true
		);

		FacebookServerSideEvent::get_instance()->track( $server_event );
	}

	/**
	 * Injects lead event code into the footer.
	 *
	 * This method retrieves tracked events from the FacebookServerSideEvent
	 * instance and renders them into pixel code using the PixelRenderer.
	 * The resulting code is printed into the footer section of the page.
	 * If the user is an internal user, the method returns without injecting
	 * any code.
	 *
	 * @return void
	 */
	public static function injectLeadEvent() {
		if ( FacebookPluginUtils::is_internal_user() ) {
			return;
		}

		$events     =
        FacebookServerSideEvent::get_instance()->get_tracked_events();
		$pixel_code = PixelRenderer::render( $events, self::TRACKING_NAME );

		printf(
			'
<!-- Meta Pixel Event Code -->
%s
<!-- End Meta Pixel Event Code -->
      ',
			$pixel_code // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Reads the form submission data and extracts user information.
	 *
	 * This method processes the form entry and form
     * data to extract user-related
	 * information such as email, first name, last name,
     * and phone number. It also
	 * retrieves the address data, including city,
     * state, country, and postal code.
	 *
	 * If either the form entry or form data is
     * empty, an empty array is returned.
	 *
	 * @param array $entry The form entry data.
	 * @param array $form_data The form schema data.
	 *
	 * @return array An associative array
     *               containing user and address information
	 *               extracted from the form entry.
	 */
	public static function readFormData( $entry, $form_data ) {
		if ( empty( $entry ) || empty( $form_data ) ) {
			return array();
		}

        $named_index = array();

        foreach ( $form_data['fields'] as $field_name ) {
            $named_index[ strtolower( $field_name['label'] ) ]
            = $entry['fields'][ $field_name['id'] ];
        }

		$event_data = array(
			'email'      => $named_index['email'] ?? null,
			'first_name' => $named_index['name']['first'] ?? null,
			'last_name'  => $named_index['name']['last'] ?? null,
			'phone'      => $named_index['phone'] ?? null,
            'city'       => $named_index['city'] ?? null,
            'state'      => $named_index['state'] ?? null,
            'country'    => $named_index['country'] ?? null,
            'zip'        => $named_index['zip'] ?? null,
            'gender'     => $named_index['gender'] ?? null,
		);

		return $event_data;
	}
}
