<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase WordPress.Files.FileName.InvalidClassFileName
/**
 * Facebook Pixel Plugin FacebookWordPressSettingsPageTest class.
 *
 * This file contains the main logic for FacebookWordPressSettingsPageTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordPressSettingsPageTest class.
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

use FacebookPixelPlugin\Core\FacebookWordpressSettingsPage;
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordPressSettingsPageTest class.
 */
final class FacebookWordPressSettingsPageTest extends FacebookWordpressTestBase {
	/**
	 * Tests the getCustomizedFbeNotInstalledNotice method when both pixel ID
	 * and access token are missing.
	 *
	 * This test verifies that the notice message returned by the method
	 * starts with the expected prefix indicating the plugin is almost ready.
	 * It ensures that the message is correctly formatted when no pixel ID
	 * and access token are set.
	 *
	 * @return void
	 */
	public function testNotificationWithMissingPixel() {
		$this->mockFacebookWordpressOptions(
			array(
				'pixel_id'     => null,
				'access_token' => null,
			)
		);
		$settings_page   =
			new FacebookWordpressSettingsPage( 'facebook_for_wordpress' );
		$message         = $settings_page->get_customized_fbe_not_installed_notice();
		$expected_prefix = sprintf(
			'<strong>%s</strong> is almost ready.',
			FacebookPluginConfig::PLUGIN_NAME
		);
		$this->assertStringStartsWith( $expected_prefix, $message );
	}

	/**
	 * Tests the getCustomizedFbeNotInstalledNotice method when the pixel ID is set
	 * but the access token is missing.
	 *
	 * This test verifies that the notice message returned by the method
	 * starts with the expected prefix indicating the plugin is almost ready
	 * and that the Conversions API is available after installing the FBE.
	 * It ensures that the message is correctly formatted when the pixel ID
	 * is set but the access token is not set.
	 *
	 * @return void
	 */
	public function testNotificationWithValidPixelAndMissingAccessToken() {
		$this->mockFacebookWordpressOptions(
			array(
				'pixel_id'     => '1234',
				'access_token' => null,
			)
		);
		$settings_page   =
			new FacebookWordpressSettingsPage( 'facebook_for_wordpress' );
		$message         = $settings_page->get_customized_fbe_not_installed_notice();
		$expected_prefix = sprintf(
			'<strong>%s</strong> gives you access to the Conversions API.',
			FacebookPluginConfig::PLUGIN_NAME
		);
		$this->assertStringStartsWith( $expected_prefix, $message );
	}

	/**
	 * Tests the getCustomizedFbeNotInstalledNotice method when both pixel ID
	 * and access token are set.
	 *
	 * This test verifies that the notice message returned by the method
	 * starts with the expected prefix indicating the plugin is ready to use.
	 * It ensures that the message is correctly formatted when both pixel ID
	 * and access token are set.
	 *
	 * @return void
	 */
	public function testNotificationWithValidPixelAndValidAccessToken() {
		$this->mockFacebookWordpressOptions(
			array(
				'pixel_id'     => '1234',
				'access_token' => 'abc',
			)
		);
		$settings_page   =
			new FacebookWordpressSettingsPage( 'facebook_for_wordpress' );
		$message         = $settings_page->get_customized_fbe_not_installed_notice();
		$expected_prefix = sprintf(
			'Easily manage your connection to Meta with <strong>%s</strong>.',
			FacebookPluginConfig::PLUGIN_NAME
		);
		$this->assertStringStartsWith( $expected_prefix, $message );
	}
}
