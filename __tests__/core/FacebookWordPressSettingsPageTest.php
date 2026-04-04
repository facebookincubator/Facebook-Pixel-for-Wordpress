<?php
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
*/

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\FacebookWordpressSettingsPage;
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordPressSettingsPageTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
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
    $settings_page   = new FacebookWordpressSettingsPage(
      'facebook_for_wordpress'
    );
    $message         =
      $settings_page->get_customized_fbe_not_installed_notice();
    $expected_prefix = sprintf(
      '<strong>%s</strong> is almost ready.',
      FacebookPluginConfig::PLUGIN_NAME
    );
    $this->assertStringStartsWith( $expected_prefix, $message );
  }

  /**
   * Tests the getCustomizedFbeNotInstalledNotice method
   * when the pixel ID is set
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
    $settings_page   = new FacebookWordpressSettingsPage(
      'facebook_for_wordpress'
    );
    $message         =
      $settings_page->get_customized_fbe_not_installed_notice();
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
    $settings_page   = new FacebookWordpressSettingsPage(
      'facebook_for_wordpress'
    );
    $message         =
      $settings_page->get_customized_fbe_not_installed_notice();
    $expected_prefix = sprintf(
      'Easily manage your connection to Meta with <strong>%s</strong>.',
      FacebookPluginConfig::PLUGIN_NAME
    );
    $this->assertStringStartsWith( $expected_prefix, $message );
  }

  /**
   * Tests that FBL4B route generators produce valid URLs with nonces.
   */
  public function testFBL4BRouteGeneratorsIncludeNonces() {
    $this->mockFacebookWordpressOptions();

    \WP_Mock::userFunction(
      'wp_create_nonce',
      array( 'return' => 'test_nonce' )
    );
    \WP_Mock::userFunction(
      'admin_url',
      array( 'return' => 'https://example.com/wp-admin/admin-ajax.php' )
    );
    \WP_Mock::userFunction(
      'add_query_arg',
      array(
        'return' => function ( $args, $url ) {
          return $url . '?' . http_build_query( $args );
        },
      )
    );

    $settings_page = new FacebookWordpressSettingsPage(
      'facebook_for_wordpress'
    );

    $save_route = $settings_page->get_fbl4b_save_settings_ajax_route();
    $this->assertStringContainsString( 'save_fbl4b_settings', $save_route );
    $this->assertStringContainsString( 'test_nonce', $save_route );

    $delete_route = $settings_page->get_fbl4b_delete_settings_ajax_route();
    $this->assertStringContainsString( 'delete_fbl4b_settings', $delete_route );

    $validate_route = $settings_page->get_fbl4b_validate_token_route();
    $this->assertStringContainsString( 'fbl4b_validate_token', $validate_route );

    $business_route = $settings_page->get_fbl4b_fetch_business_id_route();
    $this->assertStringContainsString( 'fbl4b_fetch_business_id', $business_route );

    $pixels_route = $settings_page->get_fbl4b_fetch_pixels_route();
    $this->assertStringContainsString( 'fbl4b_fetch_pixels', $pixels_route );
  }

  /**
   * Tests that the FBL4B popup origin returns the correct domain.
   */
  public function testFBL4BPopupOriginDefaultDomain() {
    $this->mockFacebookWordpressOptions();

    $settings_page = new FacebookWordpressSettingsPage(
      'facebook_for_wordpress'
    );

    $reflection = new \ReflectionMethod( $settings_page, 'get_fbl4b_popup_origin' );
    $reflection->setAccessible( true );
    $origin = $reflection->invoke( $settings_page );

    $this->assertEquals( 'https://business.facebook.com', $origin );
  }

  /**
   * Tests that the FBL4B iframe URL includes app_id and config_id.
   */
  public function testFBL4BIframeUrlIncludesParams() {
    $this->mockFacebookWordpressOptions();

    $settings_page = new FacebookWordpressSettingsPage(
      'facebook_for_wordpress'
    );

    $reflection = new \ReflectionMethod( $settings_page, 'get_fbl4b_iframe_url' );
    $reflection->setAccessible( true );
    $url = $reflection->invoke( $settings_page, '12345', '67890' );

    $this->assertStringContainsString( 'fbl4b-iframe-get-started', $url );
    $this->assertStringContainsString( 'app_id=12345', $url );
    $this->assertStringContainsString( 'config_id=67890', $url );
  }
}
