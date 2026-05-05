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
   * Tests the WordPress.com update notice copy.
   *
   * @return void
   */
  public function testWpcomUpdateNoticeMessageIncludesManualInstallSteps() {
    $this->mockTranslationFunctions();
    \WP_Mock::userFunction(
      'esc_url',
      array(
        'return_arg' => 0,
      )
    );
    $settings_page = new FacebookWordpressSettingsPage(
      'facebook_for_wordpress'
    );

    $message = $settings_page->get_wpcom_update_notice_message();

    $this->assertStringContainsString(
      'Meta Pixel for WordPress is expected to be removed',
      $message
    );
    $this->assertStringContainsString(
      'wordpress.org/plugins/official-facebook-pixel/',
      $message
    );
    $this->assertStringContainsString(
      'Upload Plugin',
      $message
    );
  }

  /**
   * Tests that the WordPress.com update notice is shown only for
   * WordPress.com-hosted sites when it has not been dismissed.
   *
   * @return void
   */
  public function testWpcomUpdateNoticeVisibilityForHostedSites() {
    \WP_Mock::userFunction(
      'get_option',
      array(
        'args' => 'is_wordpress_com_hosted',
        'return' => 1,
      )
    );
    \WP_Mock::userFunction(
      'get_current_user_id',
      array(
        'return' => 123,
      )
    );
    \WP_Mock::userFunction(
      'get_user_meta',
      array(
        'args' => array(
          123,
          FacebookPluginConfig::ADMIN_IGNORE_WPCOM_UPDATE_NOTICE,
          true,
        ),
        'return' => false,
      )
    );

    $settings_page = new FacebookWordpressSettingsPage(
      'facebook_for_wordpress'
    );

    $this->assertTrue( $settings_page->should_show_wpcom_update_notice() );
  }

  /**
   * Tests that the WordPress.com update notice is hidden when the user
   * has already dismissed it.
   *
   * @return void
   */
  public function testWpcomUpdateNoticeHiddenWhenDismissed() {
    \WP_Mock::userFunction(
      'get_option',
      array(
        'args' => 'is_wordpress_com_hosted',
        'return' => 1,
      )
    );
    \WP_Mock::userFunction(
      'get_current_user_id',
      array(
        'return' => 123,
      )
    );
    \WP_Mock::userFunction(
      'get_user_meta',
      array(
        'args' => array(
          123,
          FacebookPluginConfig::ADMIN_IGNORE_WPCOM_UPDATE_NOTICE,
          true,
        ),
        'return' => true,
      )
    );

    $settings_page = new FacebookWordpressSettingsPage(
      'facebook_for_wordpress'
    );

    $this->assertFalse( $settings_page->should_show_wpcom_update_notice() );
  }

  /**
   * Tests that the WordPress.com update notice is hidden for
   * non-WordPress.com-hosted sites.
   *
   * @return void
   */
  public function testWpcomUpdateNoticeHiddenForNonHostedSites() {
    \WP_Mock::userFunction(
      'get_option',
      array(
        'args' => 'is_wordpress_com_hosted',
        'return' => 0,
      )
    );

    $settings_page = new FacebookWordpressSettingsPage(
      'facebook_for_wordpress'
    );

    $this->assertFalse( $settings_page->should_show_wpcom_update_notice() );
  }

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

  /**
   * Tests that get_meta_wc_params includes FBL4B app_id and config_id
   * when the FBL4B constants resolve to non-empty values.
   */
  public function testGetMetaWcParamsIncludesFBL4BAppIdAndConfigId() {
    $this->mockFacebookWordpressOptions();

    \WP_Mock::userFunction(
      'admin_url',
      array( 'return' => 'https://example.com/wp-admin/admin-ajax.php' )
    );
    \WP_Mock::userFunction(
      'wp_create_nonce',
      array( 'return' => 'test_nonce' )
    );
    \WP_Mock::userFunction(
      'esc_html',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );
    \WP_Mock::userFunction(
      'wp_json_encode',
      array(
        'return' => function ( $input ) {
          return json_encode( $input );
        },
      )
    );
    \WP_Mock::userFunction(
      'add_query_arg',
      array(
        'return' => function ( $args, $url ) {
          return $url . '?' . http_build_query( $args );
        },
      )
    );

    // Mock FBL4B-specific option getters on the options mock.
    $this->mocked_options->shouldReceive( 'get_fbl4b_pixel_id' )
      ->andReturn( '99999' );
    $this->mocked_options->shouldReceive( 'get_fbl4b_pixel_name' )
      ->andReturn( 'Test Pixel' );
    $this->mocked_options->shouldReceive( 'get_fbl4b_business_id' )
      ->andReturn( '88888' );
    $this->mocked_options->shouldReceive( 'get_external_business_id' )
      ->andReturn( 'fbe_wordpress_test' );
    $this->mocked_options->shouldReceive(
      'get_capi_integration_page_view_filtered'
    )->andReturn( array() );

    $settings_page = new FacebookWordpressSettingsPage(
      'facebook_for_wordpress'
    );

    $reflection = new \ReflectionMethod( $settings_page, 'get_meta_wc_params' );
    $reflection->setAccessible( true );
    $params = $reflection->invoke( $settings_page );

    // FBL4B_APP_ID and FBL4B_CONFIG_ID are non-empty class constants,
    // so the params should include FBL4B config.
    $this->assertArrayHasKey( 'fbl4bAppId', $params );
    $this->assertArrayHasKey( 'fbl4bConfigId', $params );
    $this->assertEquals(
      FacebookPluginConfig::FBL4B_APP_ID,
      $params['fbl4bAppId']
    );
    $this->assertEquals(
      FacebookPluginConfig::FBL4B_CONFIG_ID,
      $params['fbl4bConfigId']
    );
    $this->assertArrayHasKey( 'fbl4bSaveSettingsRoute', $params );
    $this->assertArrayHasKey( 'fbl4bDeleteSettingsRoute', $params );
  }

  /**
   * Mocks translation functions used by notice generation.
   *
   * @return void
   */
  private function mockTranslationFunctions() {
    \WP_Mock::userFunction(
      '__',
      array(
        'return_arg' => 0,
      )
    );
    \WP_Mock::userFunction(
      'esc_html__',
      array(
        'return_arg' => 0,
      )
    );
  }
}
