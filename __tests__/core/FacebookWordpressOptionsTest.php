<?php
/**
 * Facebook Pixel Plugin FacebookWordpressOptionsTest class.
 *
 * This file contains the main logic for FacebookWordpressOptionsTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressOptionsTest class.
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
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressOptionsTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressOptionsTest extends FacebookWordpressTestBase {
	/**
	 * Tests that the FacebookWordpressOptions class can be initialized.
	 *
	 * Asserts that:
	 *  - The correct pixel ID is stored.
	 *  - The correct plugin version is stored.
	 *  - The correct source is stored.
	 *  - The correct version is stored.
	 *  - The correct conditions are met.
	 *  - The correct hooks are added.
	 */
	public function testCanInitialize() {
		self::mockGetOption( '1234', '1234' );
		self::mockEscJs( '1234' );
		self::mockGetTransientAAMSettings(
			'1234',
			true,
			AAMSettingsFields::get_all_fields()
		);
		\WP_Mock::expectActionAdded(
			'init',
			array(
				'FacebookPixelPlugin\\Core\\FacebookWordpressOptions',
				'register_user_info',
			),
			0
		);
		FacebookWordpressOptions::initialize();

		$pixel_id     = FacebookWordpressOptions::get_pixel_id();
		$version_info = FacebookWordpressOptions::get_version_info();

		$this->assertEquals( $pixel_id, '1234' );
		$this->assertEquals(
			$version_info['pluginVersion'],
			FacebookPluginConfig::PLUGIN_VERSION
		);
		$this->assertEquals( $version_info['source'], FacebookPluginConfig::SOURCE );
		$this->assertEquals( $version_info['version'], '1.0' );
		$this->assertConditionsMet();
		$this->assertHooksAdded();
	}

	/**
	 * Tests that the registerUserInfo method correctly registers the user information.
	 *
	 * Asserts that:
	 *  - The user information is correctly registered.
	 *  - The user information is accessible via the get_user_info method.
	 *  - The user information matches the expected values.
	 */
	public function testCanRegisterUserInfo() {
		self::mockWpGetCurrentUser( '1234' );

		self::mockGetOption( '1234', '1234', '1' );
		self::mockEscJs( '1234' );
		self::mockGetTransientAAMSettings(
			'1234',
			true,
			AAMSettingsFields::get_all_fields()
		);
		\WP_Mock::expectActionAdded(
			'init',
			array(
				'FacebookPixelPlugin\\Core\\FacebookWordpressOptions',
				'register_user_info',
			),
			0
		);
		FacebookWordpressOptions::initialize();

		FacebookWordpressOptions::registerUserInfo();
		$user_info = FacebookWordpressOptions::get_user_info();
		$this->assertEquals( $user_info['em'], 'foo@foo.com' );
		$this->assertEquals( $user_info['fn'], 'john' );
		$this->assertEquals( $user_info['ln'], 'doe' );
	}

	/**
	 * Tests that the registerUserInfo method returns an empty array when the user ID is not available.
	 *
	 * Asserts that:
	 *  - The user information is an empty array.
	 */
	public function testCannotRegisterUserInfoWithoutUserId() {
		self::mockWpGetCurrentUser();

		self::mockGetOption( '1234', '1234' );
		self::mockEscJs( '1234' );
		self::mockGetTransientAAMSettings(
			'1234',
			true,
			AAMSettingsFields::get_all_fields()
		);
		\WP_Mock::expectActionAdded(
			'init',
			array(
				'FacebookPixelPlugin\\Core\\FacebookWordpressOptions',
				'register_user_info',
			),
			0
		);
		FacebookWordpressOptions::initialize();

		FacebookWordpressOptions::registerUserInfo();
		$user_info = FacebookWordpressOptions::get_user_info();
		$this->assertEquals( \count( $user_info ), 0 );
	}

	/**
	 * Tests that the registerUserInfo method does not register the user information
	 * when the 'Use Personal Identifiable Information (PII)' setting is disabled.
	 *
	 * Asserts that:
	 *  - The user information is an empty array.
	 */
	public function testCannotRegisterUserInfoWithoutUsePII() {
		self::mockWpGetCurrentUser( '1234' );

		self::mockGetOption( '1234', '1234' );
		self::mockEscJs();
		self::mockGetTransientAAMSettings(
			'1234',
			false,
			AAMSettingsFields::get_all_fields()
		);
		\WP_Mock::expectActionAdded(
			'init',
			array(
				'FacebookPixelPlugin\\Core\\FacebookWordpressOptions',
				'register_user_info',
			),
			0
		);
		FacebookWordpressOptions::initialize();

		FacebookWordpressOptions::registerUserInfo();
		$user_info = FacebookWordpressOptions::get_user_info();

		$this->assertEquals( \count( $user_info ), 0 );
	}

	/**
	 * Tests that the set_version_info method correctly sets the version information
	 * and the get_agent_string method returns the agent string with the correct
	 * version information.
	 *
	 * Asserts that:
	 *  - The version information is correctly set.
	 *  - The agent string is correctly constructed with the version information.
	 */
	public function testCanSetVersionInfoAndGetAgentString() {
		FacebookWordpressOptions::set_version_info();

		$version_info = FacebookWordpressOptions::get_version_info();
		$this->assertEquals(
			$version_info['pluginVersion'],
			FacebookPluginConfig::PLUGIN_VERSION
		);
		$this->assertEquals( $version_info['source'], FacebookPluginConfig::SOURCE );
		$this->assertEquals( $version_info['version'], '1.1' );

		$agent_string = FacebookWordpressOptions::get_agent_string();
		$this->assertEquals(
			$agent_string,
			FacebookPluginConfig::SOURCE .
			'-1.1-' .
			FacebookPluginConfig::PLUGIN_VERSION
		);
	}

	/**
	 * Tests that the default values are correctly set.
	 *
	 * Asserts that:
	 *  - The default pixel ID is an empty string.
	 *  - The default access token is an empty string.
	 *  - The default is_fbe_installed value is '0'.
	 *  - The default external business ID is a string that starts with 'fbe_wordpress_'.
	 */
	public function testDefaultValuesAreCorrect() {
		self::mockEscJs( '' );
		self::mockGetOption();
		\WP_Mock::expectActionAdded(
			'init',
			array(
				'FacebookPixelPlugin\\Core\\FacebookWordpressOptions',
				'register_user_info',
			),
			0
		);
		FacebookWordpressOptions::initialize();

		$pixel_id             = FacebookWordpressOptions::get_pixel_id();
		$version_info         = FacebookWordpressOptions::get_version_info();
		$access_token         = FacebookWordpressOptions::get_access_token();
		$is_fbe_installed     = FacebookWordpressOptions::get_is_fbe_installed();
		$external_business_id = FacebookWordpressOptions::get_external_business_id();

		$this->assertEquals( $pixel_id, '' );
		$this->assertEquals( $access_token, '' );
		$this->assertEquals( $is_fbe_installed, '0' );
		$this->assertTrue( str_contains( $external_business_id, 'fbe_wordpress_' ) );
	}

	/**
	 * Mocks the get_option function to return the mock values for the
	 * pixel ID, access token, and is FBE installed.
	 *
	 * @param string|null $mock_pixel_id     The mock pixel ID.
	 * @param string|null $mock_access_token The mock access token.
	 * @param string      $mock_fbe_installed The mock FBE installed value.
	 */
	private function mockGetOption(
		$mock_pixel_id = null,
		$mock_access_token = null,
		$mock_fbe_installed = '0'
	) {
		\WP_Mock::userFunction(
			'get_option',
			array(
				'return' =>
				array(
					FacebookPluginConfig::PIXEL_ID_KEY     =>
						is_null( $mock_pixel_id ) ?
						FacebookWordpressOptions::get_default_pixel_id() :
						$mock_pixel_id,
					FacebookPluginConfig::ACCESS_TOKEN_KEY =>
						is_null( $mock_access_token ) ?
						FacebookWordpressOptions::get_default_access_token() :
						$mock_access_token,
					FacebookPluginConfig::IS_FBE_INSTALLED_KEY =>
						$mock_fbe_installed,
				),
			)
		);
	}

	/**
	 * Mocks the return value of get_transient for AAM settings.
	 *
	 * This function takes in the mock values for the pixel ID, enable AAM,
	 * and enabled AAM fields. It then sets up the mock for get_transient
	 * to return an array with the mock values for the AAM settings.
	 *
	 * @param string|null $pixel_id The mock pixel ID.
	 * @param bool        $enable_aam The mock enable AAM value.
	 * @param array       $aam_fields The mock enabled AAM fields.
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

	/**
	 * Mocks the esc_js function to return the provided string.
	 *
	 * This method sets up a WP_Mock for the esc_js function, ensuring that
	 * it returns the given string when called. Useful for testing purposes
	 * where the actual behavior of esc_js needs to be bypassed.
	 *
	 * @param string $value The string to be returned by the mocked esc_js function.
	 */
	private function mockEscJs( $value = '1234' ) {
		\WP_Mock::userFunction(
			'esc_js',
			array(
				'args'   => $value,
				'return' => $value,
			)
		);
	}

	/**
	 * Mocks the wp_get_current_user function to return a user object with specified attributes.
	 *
	 * This method sets up a WP_Mock for the wp_get_current_user function, returning a user
	 * object with the given user ID. The user object also includes predefined values for
	 * email, first name, and last name, which are useful for testing scenarios where user
	 * information is required.
	 *
	 * @param int $user_id The ID of the user to be returned by the mocked wp_get_current_user function.
	 */
	private function mockWpGetCurrentUser( $user_id = 0 ) {
		\WP_Mock::userFunction(
			'wp_get_current_user',
			array(
				'return' => (object) array(
					'ID'             => $user_id,
					'user_email'     => 'foo@foo.com',
					'user_firstname' => 'John',
					'user_lastname'  => 'Doe',
				),
			)
		);
	}
}
