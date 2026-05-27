<?php
/**
 * Facebook Pixel Plugin FacebookWordpressSettingsRecorderTest class.
 *
 * This file contains the main logic for FacebookWordpressSettingsRecorderTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressSettingsRecorderTest class.
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

use FacebookPixelPlugin\Core\FacebookWordpressSettingsRecorder;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\FacebookPluginConfig;

/**
 * FacebookWordpressSettingsRecorderTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressSettingsRecorderTest extends FacebookWordpressTestBase {
  /**
   * Verifies that the Ajax actions are added to WordPress.
   *
   * We're verifying that the save_fbe_settings
   * and delete_fbe_settings methods are
   * properly added as Ajax actions in WordPress.
   *
   * @covers FacebookWordpressSettingsRecorder::init
   */
  public function testAjaxActionsAdded() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();
    \WP_Mock::expectActionAdded(
      'wp_ajax_save_fbe_settings',
      array( $settings_recorder, 'save_fbe_settings' )
    );
    \WP_Mock::expectActionAdded(
      'wp_ajax_delete_fbe_settings',
      array( $settings_recorder, 'delete_fbe_settings' )
    );
    $settings_recorder->init();
  }

  /**
   * Mocks WordPress functions for tests.
   *
   * This function sets up stubs for WordPress functions that are used in the
   * FacebookWordpressSettingsRecorder class. The stubs are set up to return
   * true for all invocations.
   *
   * @return void
   */
  public function mockWordPressFunctions() {
    \WP_Mock::userFunction(
      'current_user_can',
      array(
        'return' => true,
      )
    );
    \WP_Mock::userFunction(
      'update_option',
      array(
        'return' => true,
      )
    );
    \WP_Mock::userFunction(
      'wp_send_json',
      array(
        'return' => true,
      )
    );
    \WP_Mock::userFunction(
      'check_admin_referer',
      array(
        'return' => true,
      )
    );
    \WP_Mock::userFunction(
      'delete_transient',
      array(
        'return' => true,
      )
    );
    \WP_Mock::userFunction(
      'delete_metadata',
      array(
        'return' => true,
      )
    );
  }

  /**
   * Tests that invalid settings are not saved.
   *
   * This test verifies that the FacebookWordpressSettingsRecorder
   * class does not save
   * settings when the pixel ID, access token, or external business
   * ID contain invalid
   * values. The test case sets up invalid values for the
   * $_POST superglobal and
   * verifies that the save_fbe_settings method returns an error.
   *
   * @covers FacebookWordpressSettingsRecorder::save_fbe_settings
   */
  public function testDoesNotSaveInvalidSettings() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();
    self::mockWordPressFunctions();
    global $_POST;
    $_POST['pixelId']            = '</script><script>alert(1)</script>';
    $_POST['accessToken']        = '</script><script>alert(2)</script>';
    $_POST['externalBusinessId'] = '</script><script>alert(3)</script>';

    \WP_Mock::userFunction(
      'wp_unslash',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return_in_order' => array(
          '',
          '',
          '',
        ),
      )
    );

    $expected_json = array(
      'success' => false,
      'msg'     => 'Invalid values',
    );
    $result        = $settings_recorder->save_fbe_settings();
    $this->assertEquals( $expected_json, $result );
  }

  /**
   * Tests that settings are saved when the current user is an administrator.
   *
   * This test verifies that the FacebookWordpressSettingsRecorder class saves
   * settings when the current user is an administrator.
   * It sets up valid values
   * for the $_POST superglobal and calls the save_fbe_settings method.
   * The test
   * verifies that the settings are saved correctly by comparing the result
   * with the expected output.
   *
   * @covers FacebookWordpressSettingsRecorder::save_fbe_settings
   */
  public function testSaveSettingsWithAdmin() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();
    self::mockWordPressFunctions();
    global $_POST;
    $_POST['pixelId']            = '123';
    $_POST['accessToken']        = 'ABC123XYZ';
    $_POST['externalBusinessId'] = 'fbe_wordpress_123_abc';
    if ( isset( $_POST['pixelId'] ) && isset(
      $_POST['accessToken']
    ) && isset( $_POST['externalBusinessId'] ) ) {
      \WP_Mock::userFunction(
        'sanitize_text_field',
        array(
          'return_in_order' => array(
            $_POST['pixelId'],
            $_POST['accessToken'],
            $_POST['externalBusinessId'],
          ),
        )
      );
      $expected_json = array(
        'success' => true,
        'msg'     => array(
          FacebookPluginConfig::PIXEL_ID_KEY         => $_POST['pixelId'],
          FacebookPluginConfig::ACCESS_TOKEN_KEY     => $_POST['accessToken'],
          FacebookPluginConfig::EXTERNAL_BUSINESS_ID_KEY => $_POST['externalBusinessId'],
          FacebookPluginConfig::IS_FBE_INSTALLED_KEY => '1',
        ),
      );

      \WP_Mock::userFunction(
        'wp_unslash',
        array(
          'args'   => array( \Mockery::any() ),
          'return' => function ( $input ) {
            return $input;
          },
        )
      );

      $result = $settings_recorder->save_fbe_settings();
      $this->assertEquals( $expected_json, $result );
    }
  }

  /**
   * Verifies that the FBL4B AJAX actions are added to WordPress.
   */
  public function testFBL4BAjaxActionsAdded() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();
    \WP_Mock::expectActionAdded(
      'wp_ajax_save_fbl4b_settings',
      array( $settings_recorder, 'save_fbl4b_settings' )
    );
    \WP_Mock::expectActionAdded(
      'wp_ajax_delete_fbl4b_settings',
      array( $settings_recorder, 'delete_fbl4b_settings' )
    );
    $settings_recorder->init();
  }

  /**
   * Tests that FBL4B settings are saved correctly on initial save.
   */
  public function testSaveFBL4BSettingsInitialSave() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();
    self::mockWordPressFunctions();
    self::mockSanitizeAndUnslash();

    \WP_Mock::userFunction(
      'wp_salt',
      array(
        'args'   => array( 'auth' ),
        'return' => 'test-salt-key',
      )
    );

    // Token sent via Authorization: Bearer header (not POST body)
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer EAABsbCS1iBABO0TestToken';

    global $_POST;
    $_POST['pixelId']     = '12345';
    $_POST['pixelName']   = 'Test Pixel';
    $_POST['businessId']  = '67890';

    \WP_Mock::userFunction(
      'update_option',
      array( 'return' => true )
    );

    $result = $settings_recorder->save_fbl4b_settings();

    $this->assertTrue( $result['success'] );
    $this->assertEquals( 'FBL4B settings saved', $result['msg'] );
  }

  /**
   * Tests that FBL4B partial update merges with existing settings.
   */
  public function testSaveFBL4BSettingsPartialUpdate() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();
    self::mockWordPressFunctions();
    self::mockSanitizeAndUnslash();

    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => 'encrypted_token',
          FacebookPluginConfig::FBL4B_BUSINESS_ID_KEY  => '67890',
        ),
      )
    );

    global $_POST;
    $_POST['accessToken'] = '';
    $_POST['pixelId']     = '99999';
    $_POST['pixelName']   = 'Selected Pixel';
    $_POST['businessId']  = '';

    $result = $settings_recorder->save_fbl4b_settings();

    $this->assertTrue( $result['success'] );
    $this->assertArrayHasKey( FacebookPluginConfig::FBL4B_PIXEL_ID_KEY, $result['msg'] );
    $this->assertEquals( '99999', $result['msg'][ FacebookPluginConfig::FBL4B_PIXEL_ID_KEY ] );
  }

  /**
   * Tests that FBL4B save rejects unauthorized users.
   */
  public function testSaveFBL4BSettingsUnauthorized() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();

    \WP_Mock::userFunction(
      'current_user_can',
      array( 'return' => false )
    );
    \WP_Mock::userFunction(
      'wp_send_json',
      array( 'return' => true )
    );

    $result = $settings_recorder->save_fbl4b_settings();

    $this->assertFalse( $result['success'] );
  }

  /**
   * Tests that partial update with no existing data returns invalid.
   */
  public function testSaveFBL4BSettingsInvalidNoExistingData() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();
    self::mockWordPressFunctions();
    self::mockSanitizeAndUnslash();

    \WP_Mock::userFunction(
      'get_option',
      array( 'return' => array() )
    );

    global $_POST;
    $_POST['accessToken'] = '';
    $_POST['pixelId']     = '12345';
    $_POST['pixelName']   = '';
    $_POST['businessId']  = '';

    $result = $settings_recorder->save_fbl4b_settings();

    $this->assertFalse( $result['success'] );
  }

  /**
   * Tests that FBL4B settings are deleted successfully.
   */
  public function testDeleteFBL4BSettings() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();
    self::mockWordPressFunctions();

    \WP_Mock::userFunction(
      'delete_option',
      array( 'return' => true )
    );
    \WP_Mock::userFunction(
      'delete_transient',
      array( 'return' => true )
    );
    \WP_Mock::userFunction(
      'get_current_user_id',
      array( 'return' => 1 )
    );
    \WP_Mock::userFunction(
      'delete_user_meta',
      array( 'return' => true )
    );

    $result = $settings_recorder->delete_fbl4b_settings();

    $this->assertTrue( $result['success'] );
    $this->assertEquals( 'FBL4B settings deleted', $result['msg'] );
  }

  /**
   * Tests that FBL4B delete rejects unauthorized users.
   */
  public function testDeleteFBL4BSettingsUnauthorized() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();

    \WP_Mock::userFunction(
      'current_user_can',
      array( 'return' => false )
    );
    \WP_Mock::userFunction(
      'wp_send_json',
      array( 'return' => true )
    );

    $result = $settings_recorder->delete_fbl4b_settings();

    $this->assertFalse( $result['success'] );
  }

  /**
   * Tests that FBL4B AJAX actions for proxy endpoints are registered.
   */
  public function testFBL4BProxyAjaxActionsAdded() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();
    \WP_Mock::expectActionAdded(
      'wp_ajax_fbl4b_validate_token',
      array( $settings_recorder, 'fbl4b_validate_token' )
    );
    \WP_Mock::expectActionAdded(
      'wp_ajax_fbl4b_fetch_business_id',
      array( $settings_recorder, 'fbl4b_fetch_business_id' )
    );
    \WP_Mock::expectActionAdded(
      'wp_ajax_fbl4b_fetch_pixels',
      array( $settings_recorder, 'fbl4b_fetch_pixels' )
    );
    $settings_recorder->init();
  }

  /**
   * Tests that validate_token returns invalid when no token is stored.
   */
  public function testValidateTokenReturnsInvalidWhenNoToken() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();

    \WP_Mock::userFunction(
      'current_user_can',
      array( 'return' => true )
    );
    \WP_Mock::userFunction(
      'check_admin_referer',
      array( 'return' => true )
    );
    \WP_Mock::userFunction(
      'get_option',
      array( 'return' => array() )
    );

    $response_data = null;
    \WP_Mock::userFunction(
      'wp_send_json_success',
      array(
        'return' => function ( $data ) use ( &$response_data ) {
          $response_data = $data;
        },
      )
    );

    $settings_recorder->fbl4b_validate_token();

    $this->assertFalse( $response_data['valid'] );
    $this->assertNull( $response_data['client_business_id'] );
  }

  /**
   * Tests that fetch_business_id returns error when no token is stored.
   */
  public function testFetchBusinessIdReturnsErrorWhenNoToken() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();

    \WP_Mock::userFunction(
      'current_user_can',
      array( 'return' => true )
    );
    \WP_Mock::userFunction(
      'check_admin_referer',
      array( 'return' => true )
    );
    \WP_Mock::userFunction(
      'get_option',
      array( 'return' => array() )
    );

    $error_msg = null;
    \WP_Mock::userFunction(
      'wp_send_json_error',
      array(
        'return' => function ( $msg ) use ( &$error_msg ) {
          $error_msg = $msg;
        },
      )
    );

    $settings_recorder->fbl4b_fetch_business_id();

    $this->assertEquals( 'No access token stored', $error_msg );
  }

  /**
   * Tests that fetch_pixels returns error when parameters are missing.
   */
  public function testFetchPixelsReturnsErrorWhenMissingParams() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();

    \WP_Mock::userFunction(
      'current_user_can',
      array( 'return' => true )
    );
    \WP_Mock::userFunction(
      'check_admin_referer',
      array( 'return' => true )
    );
    \WP_Mock::userFunction(
      'get_option',
      array( 'return' => array() )
    );
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );
    \WP_Mock::userFunction(
      'wp_unslash',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    global $_POST;
    $_POST['businessId'] = '';

    $error_msg = null;
    \WP_Mock::userFunction(
      'wp_send_json_error',
      array(
        'return' => function ( $msg ) use ( &$error_msg ) {
          $error_msg = $msg;
        },
      )
    );

    $settings_recorder->fbl4b_fetch_pixels();

    $this->assertEquals( 'Missing parameters', $error_msg );
  }

  /**
   * Tests that Graph API base URL returns correct value.
   */
  public function testGraphApiBaseUrl() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();
    $reflection = new \ReflectionMethod( $settings_recorder, 'get_graph_api_base_url' );
    if ( PHP_VERSION_ID < 80100 ) {
      $reflection->setAccessible( true );
    }
    $url = $reflection->invoke( $settings_recorder );

    $this->assertEquals( 'https://graph.facebook.com/v25.0', $url );
  }

  /**
   * Tests that fetch_pixels deduplicates owned and client pixels.
   * Owned pixels should appear first, duplicates from client_pixels skipped.
   */
  public function testFetchPixelsDeduplicatesOwnedAndClient() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();
    self::mockProxyEndpointBase();

    $this->mockWpSalt();
    $encrypted_token = \FacebookPixelPlugin\Core\FacebookWordpressOptions::encrypt_token( 'test_token' );
    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => $encrypted_token,
        ),
      )
    );

    global $_POST;
    $_POST['businessId'] = '12345';

    // Mock wp_remote_get to return different responses for owned vs client
    \WP_Mock::userFunction(
      'wp_remote_get',
      array(
        'return_in_order' => array(
          // owned_pixels response
          array( 'body' => '{"data":[{"id":"111","name":"Pixel A"},{"id":"222","name":"Pixel B"}]}', 'response' => array( 'code' => 200 ) ),
          // client_pixels response — includes duplicate 222
          array( 'body' => '{"data":[{"id":"222","name":"Pixel B Dup"},{"id":"333","name":"Pixel C"}]}', 'response' => array( 'code' => 200 ) ),
        ),
      )
    );
    \WP_Mock::userFunction(
      'wp_remote_retrieve_response_code',
      array(
        'return' => 200,
      )
    );
    \WP_Mock::userFunction(
      'wp_remote_retrieve_body',
      array(
        'return_in_order' => array(
          '{"data":[{"id":"111","name":"Pixel A"},{"id":"222","name":"Pixel B"}]}',
          '{"data":[{"id":"222","name":"Pixel B Dup"},{"id":"333","name":"Pixel C"}]}',
        ),
      )
    );
    \WP_Mock::userFunction(
      'is_wp_error',
      array( 'return' => false )
    );

    $response_data = null;
    \WP_Mock::userFunction(
      'wp_send_json_success',
      array(
        'return' => function ( $data ) use ( &$response_data ) {
          $response_data = $data;
        },
      )
    );

    $settings_recorder->fbl4b_fetch_pixels();

    $this->assertCount( 3, $response_data['data'] );
    $this->assertEquals( '111', $response_data['data'][0]['id'] );
    $this->assertEquals( '222', $response_data['data'][1]['id'] );
    $this->assertEquals( 'Pixel B', $response_data['data'][1]['name'] ); // owned version, not dup
    $this->assertEquals( '333', $response_data['data'][2]['id'] );
  }

  /**
   * Tests that fetch_pixels returns no_pixels error when both endpoints return empty.
   */
  public function testFetchPixelsReturnsNoPixelsWhenBothEmpty() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();
    self::mockProxyEndpointBase();

    $this->mockWpSalt();
    $encrypted_token = \FacebookPixelPlugin\Core\FacebookWordpressOptions::encrypt_token( 'test_token' );
    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => $encrypted_token,
        ),
      )
    );

    global $_POST;
    $_POST['businessId'] = '12345';

    \WP_Mock::userFunction(
      'wp_remote_get',
      array(
        'return' => array( 'body' => '{"data":[]}', 'response' => array( 'code' => 200 ) ),
      )
    );
    \WP_Mock::userFunction(
      'wp_remote_retrieve_response_code',
      array( 'return' => 200 )
    );
    \WP_Mock::userFunction(
      'wp_remote_retrieve_body',
      array( 'return' => '{"data":[]}' )
    );
    \WP_Mock::userFunction(
      'is_wp_error',
      array( 'return' => false )
    );

    $error_data = null;
    \WP_Mock::userFunction(
      'wp_send_json_error',
      array(
        'return' => function ( $data ) use ( &$error_data ) {
          $error_data = $data;
        },
      )
    );

    $settings_recorder->fbl4b_fetch_pixels();

    $this->assertEquals( 'no_pixels', $error_data['code'] );
  }

  /**
   * Tests that fetch_pixels works when only owned pixels exist.
   */
  public function testFetchPixelsWorksWithOnlyOwnedPixels() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();
    self::mockProxyEndpointBase();

    $this->mockWpSalt();
    $encrypted_token = \FacebookPixelPlugin\Core\FacebookWordpressOptions::encrypt_token( 'test_token' );
    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => $encrypted_token,
        ),
      )
    );

    global $_POST;
    $_POST['businessId'] = '12345';

    \WP_Mock::userFunction(
      'wp_remote_get',
      array(
        'return_in_order' => array(
          array( 'body' => '{"data":[{"id":"111","name":"Only Pixel"}]}', 'response' => array( 'code' => 200 ) ),
          array( 'body' => '{"data":[]}', 'response' => array( 'code' => 200 ) ),
        ),
      )
    );
    \WP_Mock::userFunction(
      'wp_remote_retrieve_response_code',
      array( 'return' => 200 )
    );
    \WP_Mock::userFunction(
      'wp_remote_retrieve_body',
      array(
        'return_in_order' => array(
          '{"data":[{"id":"111","name":"Only Pixel"}]}',
          '{"data":[]}',
        ),
      )
    );
    \WP_Mock::userFunction(
      'is_wp_error',
      array( 'return' => false )
    );

    $response_data = null;
    \WP_Mock::userFunction(
      'wp_send_json_success',
      array(
        'return' => function ( $data ) use ( &$response_data ) {
          $response_data = $data;
        },
      )
    );

    $settings_recorder->fbl4b_fetch_pixels();

    $this->assertCount( 1, $response_data['data'] );
    $this->assertEquals( '111', $response_data['data'][0]['id'] );
  }

  /**
   * Mocks common functions needed for proxy endpoint tests.
   */
  private static function mockProxyEndpointBase() {
    \WP_Mock::userFunction(
      'current_user_can',
      array( 'return' => true )
    );
    \WP_Mock::userFunction(
      'check_admin_referer',
      array( 'return' => true )
    );
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );
    \WP_Mock::userFunction(
      'wp_unslash',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );
  }

  /**
   * Mocks wp_salt('auth') for token encryption.
   */
  private function mockWpSalt() {
    \WP_Mock::userFunction(
      'wp_salt',
      array(
        'args'   => array( 'auth' ),
        'return' => 'test-salt-key-for-encryption',
      )
    );
  }

  /**
   * Mocks sanitize_text_field and wp_unslash for AJAX tests.
   */
  private static function mockSanitizeAndUnslash() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );
    \WP_Mock::userFunction(
      'wp_unslash',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );
    \WP_Mock::userFunction(
      'delete_transient',
      array(
        'return' => true,
      )
    );
    \WP_Mock::userFunction(
      'delete_metadata',
      array(
        'return' => true,
      )
    );
  }

  // =========================================
  // Nonce Rejection Tests
  // =========================================

  /**
   * Tests that save_fbl4b_settings dies when nonce is invalid.
   * WordPress's check_admin_referer() calls wp_nonce_ays() then die()
   * on failure, which we simulate by throwing an exception.
   */
  public function testSaveFBL4BSettingsRejectsInvalidNonce() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();

    \WP_Mock::userFunction(
      'current_user_can',
      array( 'return' => true )
    );
    \WP_Mock::userFunction(
      'check_admin_referer',
      array(
        'return' => function () {
          throw new \Exception( 'Nonce verification failed' );
        },
      )
    );

    $this->expectException( \Exception::class );
    $this->expectExceptionMessage( 'Nonce verification failed' );

    $settings_recorder->save_fbl4b_settings();
  }

  /**
   * Tests that delete_fbl4b_settings dies when nonce is invalid.
   */
  public function testDeleteFBL4BSettingsRejectsInvalidNonce() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();

    \WP_Mock::userFunction(
      'current_user_can',
      array( 'return' => true )
    );
    \WP_Mock::userFunction(
      'check_admin_referer',
      array(
        'return' => function () {
          throw new \Exception( 'Nonce verification failed' );
        },
      )
    );

    $this->expectException( \Exception::class );
    $this->expectExceptionMessage( 'Nonce verification failed' );

    $settings_recorder->delete_fbl4b_settings();
  }

  // =========================================
  // Upgrade Notice Flag Tests
  // =========================================

  /**
   * Tests that delete_fbl4b_settings clears the upgrade notice dismiss flag
   * so the banner reappears when the user falls back to MBE.
   */
  public function testDeleteFBL4BSettingsClearsDismissFlag() {
    $settings_recorder = new FacebookWordpressSettingsRecorder();
    self::mockWordPressFunctions();

    \WP_Mock::userFunction(
      'delete_option',
      array( 'return' => true )
    );
    \WP_Mock::userFunction(
      'delete_transient',
      array( 'return' => true )
    );

    $flag_cleared = false;
    \WP_Mock::userFunction(
      'get_current_user_id',
      array( 'return' => 42 )
    );
    \WP_Mock::userFunction(
      'delete_user_meta',
      array(
        'return' => function ( $user_id, $meta_key )
          use ( &$flag_cleared ) {
          if ( FacebookPluginConfig::ADMIN_IGNORE_FBL4B_UPGRADE_NOTICE
            === $meta_key && 42 === $user_id ) {
            $flag_cleared = true;
          }
          return true;
        },
      )
    );

    $result = $settings_recorder->delete_fbl4b_settings();

    $this->assertTrue( $result['success'] );
    $this->assertTrue(
      $flag_cleared,
      'Expected delete_user_meta to clear the dismiss flag'
    );
  }
}
