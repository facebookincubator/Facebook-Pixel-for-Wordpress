<?php
/**
 * Facebook Pixel Plugin FacebookWordpressPixelInjectionTest class.
 *
 * This file contains the main logic for FacebookWordpressPixelInjectionTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressPixelInjectionTest class.
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

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\AAMSettingsFields;
use FacebookPixelPlugin\Core\FacebookWordpressPixelInjection;
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressPixelInjectionTest class.
 */
final class FacebookWordpressPixelInjectionTest extends FacebookWordpressTestBase {
	/**
	 * List of supported integrations.
	 *
	 * @var array
	 */
	private static $integrations = array(
		'FacebookWordpressCalderaForm',
		'FacebookWordpressContactForm7',
		'FacebookWordpressEasyDigitalDownloads',
		'FacebookWordpressFormidableForm',
		'FacebookWordpressMailchimpForWp',
		'FacebookWordpressNinjaForms',
		'FacebookWordpressWPECommerce',
	);

	/**
	 * Tests the inject method from the FacebookWordpressPixelInjection class.
	 *
	 * Verifies that the correct WordPress actions are added for the
	 * inject_pixel_code and inject_pixel_noscript_code methods. Also verifies
	 * that the sendServerEvents method is not added as an action.
	 *
	 * Checks that each integration injects the correct Pixel code by verifying
	 * that the inject_pixel_code method is called on each integration class.
	 *
	 * @return void
	 */
	public function testPixelInjection() {
		self::mockGetOption( 1234 );
		$injection_obj = new FacebookWordpressPixelInjection();
		\WP_Mock::expectActionAdded(
			'wp_head',
			array( $injection_obj, 'inject_pixel_code' )
		);
		\WP_Mock::expectActionAdded(
			'wp_head',
			array( $injection_obj, 'inject_pixel_noscript_code' )
		);

		$spies = array();
		foreach ( self::$integrations as $index => $integration ) {
			$spies[] = \Mockery::spy(
				'alias:FacebookPixelPlugin\\Integration\\' . $integration
			);
		}

		\WP_Mock::expectActionNotAdded(
			'shutdown',
			array( $injection_obj, 'sendServerEvents' )
		);

		self::mockGetTransientAAMSettings(
			1234,
			false,
			AAMSettingsFields::get_all_fields()
		);

		FacebookWordpressOptions::initialize();
		$injection_obj->inject();

		foreach ( $spies as $index => $spy ) {
			$spy->shouldHaveReceived( 'inject_pixel_code' );
		}
	}

	/**
	 * Test that the FacebookWordpressPixelInjection class injects the
	 * send_pending_events method into the wp_footer action when the
	 * send_server_events option is set to true.
	 *
	 * @return void
	 */
	public function testServerEventSendingInjection() {
		self::mockGetOption( 1234, 'abc' );
		self::mockGetTransientAAMSettings(
			'1234',
			false,
			AAMSettingsFields::get_all_fields()
		);
		$injection_obj = new FacebookWordpressPixelInjection();
		\WP_Mock::expectActionAdded(
			'wp_footer',
			array( $injection_obj, 'send_pending_events' )
		);
		FacebookWordpressOptions::initialize();
		$injection_obj->inject();
	}

	/**
	 * Mocks the get_option function to return specified mock values.
	 *
	 * @param string $mock_pixel_id     The mock pixel ID to return.
	 * @param string $mock_access_token The mock access token to return.
	 *
	 * This method sets up a mock for the get_option function, ensuring
	 * that it returns the provided mock pixel ID and access token when
	 * called. Useful for testing scenarios where specific WordPress
	 * options need to be simulated.
	 */
	private function mockGetOption(
		$mock_pixel_id = '',
		$mock_access_token = ''
	) {
		\WP_Mock::userFunction(
			'get_option',
			array(
				'return' =>
				array(
					FacebookPluginConfig::PIXEL_ID_KEY     => $mock_pixel_id,
					FacebookPluginConfig::ACCESS_TOKEN_KEY => $mock_access_token,
				),
			)
		);
	}

	/**
	 * Mocks the return value of get_transient for AAM settings.
	 *
	 * This function sets up a mock for the get_transient function to return
	 * specific AAM settings. It allows testing scenarios that involve AAM
	 * settings without relying on actual WordPress transients.
	 *
	 * @param string|null $pixel_id The mock pixel ID to return.
	 * @param bool        $enable_aam Whether to mock AAM as enabled.
	 * @param array       $aam_fields The mock enabled AAM fields.
	 *
	 * @return void
	 */
	private function mockGetTransientAAMSettings(
		$pixel_id = null,
		$enable_aam = false,
		$aam_fields = array()
	) {
		define( 'MINUTE_IN_SECONDS', 60 );
		\WP_Mock::userFunction(
			'get_transient',
			array(
				'return' => array(
					'pixelId'                        => $pixel_id,
					'enableAutomaticMatching'        => $enable_aam,
					'enabledAutomaticMatchingFields' => $aam_fields,
				),
			)
		);
	}
}
