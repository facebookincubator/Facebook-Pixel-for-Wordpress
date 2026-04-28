<?php
/**
 * Facebook Pixel Plugin FacebookWordpressOptionsFBL4BTest class.
 *
 * Tests for FBL4B (Facebook Login for Business) methods in
 * FacebookWordpressOptions: token encryption.
 *
 * @package FacebookPixelPlugin
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

use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressOptionsFBL4BTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressOptionsFBL4BTest extends FacebookWordpressTestBase {

  /**
   * Tests that encrypt/decrypt round-trip preserves the original token.
   */
  public function testEncryptDecryptRoundTrip() {
    $this->mockWpSalt();
    $token     = 'EAABsbCS1iBABO0abc123def456TestToken';
    $encrypted = FacebookWordpressOptions::encrypt_token( $token );
    $decrypted = FacebookWordpressOptions::decrypt_token( $encrypted );

    $this->assertEquals( $token, $decrypted );
  }

  /**
   * Tests that two encryptions of the same token produce different
   * ciphertext due to random IV generation.
   */
  public function testEncryptProducesDifferentOutputEachTime() {
    $this->mockWpSalt();
    $token      = 'EAABsbCS1iBABO0abc123def456TestToken';
    $encrypted1 = FacebookWordpressOptions::encrypt_token( $token );
    $encrypted2 = FacebookWordpressOptions::encrypt_token( $token );

    $this->assertNotEquals( $encrypted1, $encrypted2 );
  }

  /**
   * Tests that decrypting corrupted data returns false.
   */
  public function testDecryptWithCorruptedDataReturnsFalse() {
    $this->mockWpSalt();
    $result = @FacebookWordpressOptions::decrypt_token( 'not-valid-base64-data!@#$' );

    $this->assertFalse( $result );
  }

  /**
   * Tests that decrypting an empty string returns false.
   */
  public function testDecryptWithEmptyStringReturnsFalse() {
    $this->mockWpSalt();
    $result = FacebookWordpressOptions::decrypt_token( '' );

    $this->assertFalse( $result );
  }

  // =========================================
  // Connection Type Priority Tests
  // =========================================

  /**
   * Tests that connection type is 'fbl4b' when FBL4B has token AND pixel.
   */
  public function testConnectionTypeFBL4BWhenInstalled() {
    $this->mockWpSalt();
    $encrypted_token = FacebookWordpressOptions::encrypt_token( 'test_token' );

    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => $encrypted_token,
          FacebookPluginConfig::FBL4B_PIXEL_ID_KEY     => '12345',
        ),
      )
    );

    $this->assertEquals( 'fbl4b', FacebookWordpressOptions::get_connection_type() );
  }

  /**
   * Tests that connection type is 'none' when nothing is installed.
   */
  public function testConnectionTypeNoneWhenNothingInstalled() {
    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(),
      )
    );

    $this->assertEquals( 'none', FacebookWordpressOptions::get_connection_type() );
  }

  /**
   * Tests that connection type is 'mbe' when using base class mock
   * (FBL4B not installed, FBE returns default).
   */
  public function testConnectionTypeMBEWhenOnlyFBEInstalled() {
    $this->mockFacebookWordpressOptions();
    $type = FacebookWordpressOptions::get_connection_type();
    $this->assertEquals( 'mbe', $type );
  }

  /**
   * Tests that FBL4B takes priority over MBE when both are installed
   * and FBL4B has a pixel configured.
   */
  public function testFBL4BTakesPriorityOverMBE() {
    $this->mockWpSalt();
    $encrypted_token = FacebookWordpressOptions::encrypt_token( 'test_token' );

    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => $encrypted_token,
          FacebookPluginConfig::FBL4B_PIXEL_ID_KEY     => '12345',
        ),
      )
    );

    $this->assertEquals( 'fbl4b', FacebookWordpressOptions::get_connection_type() );
  }

  /**
   * Tests that MBE stays active during FBL4B setup (token saved but no pixel)
   * when an existing MBE connection exists.
   */
  public function testConnectionTypeStaysMBEDuringFBL4BSetup() {
    $mock = \Mockery::mock( 'alias:FacebookPixelPlugin\Core\FacebookWordpressOptions' );
    $mock->shouldReceive( 'get_is_fbl4b_installed' )->andReturn( true );
    $mock->shouldReceive( 'get_fbl4b_pixel_id' )->andReturn( '' );
    $mock->shouldReceive( 'get_is_fbe_installed' )->andReturn( '1' );

    $is_fbl4b = $mock->get_is_fbl4b_installed();
    $pixel_id = $mock->get_fbl4b_pixel_id();
    $is_fbe   = $mock->get_is_fbe_installed();

    // Verify the connection type logic:
    if ( $is_fbl4b && ! empty( $pixel_id ) ) {
      $type = 'fbl4b';
    } elseif ( $is_fbl4b && $is_fbe ) {
      $type = 'mbe';
    } elseif ( $is_fbl4b ) {
      $type = 'fbl4b';
    } elseif ( $is_fbe ) {
      $type = 'mbe';
    } else {
      $type = 'none';
    }
    $this->assertEquals( 'mbe', $type );
  }

  /**
   * Tests that FBL4B is active during fresh setup (token saved, no pixel,
   * no pre-existing MBE).
   */
  public function testConnectionTypeFBL4BDuringFreshSetup() {
    $this->mockWpSalt();
    $encrypted_token = FacebookWordpressOptions::encrypt_token( 'test_token' );

    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY  => $encrypted_token,
          FacebookPluginConfig::FBL4B_PIXEL_ID_KEY      => '',
          FacebookPluginConfig::IS_FBE_INSTALLED_KEY    => '0',
        ),
      )
    );

    $type = FacebookWordpressOptions::get_connection_type();
    $this->assertEquals( 'fbl4b', $type );
  }

  // =========================================
  // Bridge Methods Tests
  // =========================================

  /**
   * Tests that get_active_pixel_id returns FBL4B pixel when connected.
   */
  public function testActivePixelIdReturnsFBL4BWhenConnected() {
    $this->mockWpSalt();
    $encrypted_token = FacebookWordpressOptions::encrypt_token( 'test_token' );

    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => $encrypted_token,
          FacebookPluginConfig::FBL4B_PIXEL_ID_KEY     => '99887766',
        ),
      )
    );

    $pixel_id = FacebookWordpressOptions::get_active_pixel_id();
    $this->assertEquals( '99887766', $pixel_id );
  }

  /**
   * Tests that get_active_pixel_id falls back to MBE when no FBL4B.
   */
  public function testActivePixelIdReturnsMBEWhenNoFBL4B() {
    $this->mockFacebookWordpressOptions( array( 'pixel_id' => '5678' ) );
    $pixel_id = FacebookWordpressOptions::get_active_pixel_id();
    $this->assertEquals( '5678', $pixel_id );
  }

  /**
   * Tests that get_active_access_token returns FBL4B token when connected.
   */
  public function testActiveAccessTokenReturnsFBL4BWhenConnected() {
    $this->mockWpSalt();
    $original_token  = 'EAABsbCS1iBABO0TestFBL4BToken';
    $encrypted_token = FacebookWordpressOptions::encrypt_token( $original_token );

    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => $encrypted_token,
          FacebookPluginConfig::FBL4B_PIXEL_ID_KEY     => '12345',
        ),
      )
    );

    $token = FacebookWordpressOptions::get_active_access_token();
    $this->assertEquals( $original_token, $token );
  }

  /**
   * Tests that get_active_access_token falls back to MBE when no FBL4B.
   */
  public function testActiveAccessTokenReturnsMBEWhenNoFBL4B() {
    $this->mockFacebookWordpressOptions( array( 'access_token' => 'mbe_token' ) );
    $token = FacebookWordpressOptions::get_active_access_token();
    $this->assertEquals( 'mbe_token', $token );
  }

  // =========================================
  // FBL4B Getter Tests
  // =========================================

  /**
   * Tests get_is_fbl4b_installed returns true when encrypted token exists.
   */
  public function testIsFBL4BInstalledWithToken() {
    $this->mockWpSalt();
    $encrypted_token = FacebookWordpressOptions::encrypt_token( 'test_token' );

    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => $encrypted_token,
        ),
      )
    );

    $this->assertTrue( FacebookWordpressOptions::get_is_fbl4b_installed() );
  }

  /**
   * Tests get_is_fbl4b_installed returns false when no token.
   */
  public function testIsFBL4BInstalledWithoutToken() {
    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(),
      )
    );

    $this->assertFalse( FacebookWordpressOptions::get_is_fbl4b_installed() );
  }

  /**
   * Tests get_fbl4b_pixel_id returns stored pixel ID.
   */
  public function testGetFBL4BPixelId() {
    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_PIXEL_ID_KEY => '11223344',
        ),
      )
    );

    $this->assertEquals( '11223344', FacebookWordpressOptions::get_fbl4b_pixel_id() );
  }

  /**
   * Tests get_fbl4b_business_id returns stored business ID.
   */
  public function testGetFBL4BBusinessId() {
    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_BUSINESS_ID_KEY => '55667788',
        ),
      )
    );

    $this->assertEquals( '55667788', FacebookWordpressOptions::get_fbl4b_business_id() );
  }

  /**
   * Tests get_fbl4b_pixel_name returns stored pixel name.
   */
  public function testGetFBL4BPixelName() {
    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_PIXEL_NAME_KEY => 'My Test Pixel',
        ),
      )
    );

    $this->assertEquals( 'My Test Pixel', FacebookWordpressOptions::get_fbl4b_pixel_name() );
  }

  /**
   * Tests get_fbl4b_access_token returns decrypted token.
   */
  public function testGetFBL4BAccessTokenDecrypts() {
    $this->mockWpSalt();
    $original_token  = 'EAABsbCS1iBABO0SecretToken123';
    $encrypted_token = FacebookWordpressOptions::encrypt_token( $original_token );

    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => $encrypted_token,
        ),
      )
    );

    $this->assertEquals(
      $original_token,
      FacebookWordpressOptions::get_fbl4b_access_token()
    );
  }

  /**
   * Tests get_fbl4b_access_token returns empty string when not set.
   */
  public function testGetFBL4BAccessTokenEmptyWhenNotSet() {
    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(),
      )
    );

    $this->assertEquals( '', FacebookWordpressOptions::get_fbl4b_access_token() );
  }

  // =========================================
  // AAM Settings Tests
  // =========================================

  /**
   * Tests that set_fbl4b_based_aam_settings uses the FBL4B pixel when cached.
   */
  public function testSetFBL4BBasedAAMSettingsUsesCachedTransient() {
    $this->mockWpSalt();
    $encrypted_token = FacebookWordpressOptions::encrypt_token( 'test_token' );

    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => $encrypted_token,
          FacebookPluginConfig::FBL4B_PIXEL_ID_KEY     => '55555',
        ),
      )
    );

    \WP_Mock::userFunction(
      'get_transient',
      array(
        'args'   => array( FacebookPluginConfig::AAM_SETTINGS_KEY ),
        'return' => array(
          'pixelId'                        => '55555',
          'enableAutomaticMatching'        => true,
          'enabledAutomaticMatchingFields' => array( 'em', 'fn', 'ln' ),
        ),
      )
    );

    // set_aam_settings is private; invoke via reflection.
    $reflection = new \ReflectionMethod(
      FacebookWordpressOptions::class,
      'set_aam_settings'
    );
    $reflection->setAccessible( true );
    $reflection->invoke( null );

    $aam_settings = FacebookWordpressOptions::get_aam_settings();

    $this->assertNotNull( $aam_settings );
    $this->assertEquals( '55555', $aam_settings->getPixelId() );
    $this->assertTrue( $aam_settings->getEnableAutomaticMatching() );
  }

  /**
   * Tests that set_fbl4b_based_aam_settings ignores cached transient
   * when pixel ID doesn't match the installed FBL4B pixel.
   */
  public function testSetFBL4BBasedAAMSettingsIgnoresMismatchedCache() {
    $this->mockWpSalt();
    $encrypted_token = FacebookWordpressOptions::encrypt_token( 'test_token' );

    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => $encrypted_token,
          FacebookPluginConfig::FBL4B_PIXEL_ID_KEY     => '55555',
        ),
      )
    );

    // Cached transient has a different pixel ID.
    \WP_Mock::userFunction(
      'get_transient',
      array(
        'args'   => array( FacebookPluginConfig::AAM_SETTINGS_KEY ),
        'return' => array(
          'pixelId'                        => '99999',
          'enableAutomaticMatching'        => true,
          'enabledAutomaticMatchingFields' => array( 'em' ),
        ),
      )
    );
    \WP_Mock::userFunction(
      'set_transient',
      array( 'return' => true )
    );

    if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
      define( 'MINUTE_IN_SECONDS', 60 );
    }

    $reflection = new \ReflectionMethod(
      FacebookWordpressOptions::class,
      'set_aam_settings'
    );
    $reflection->setAccessible( true );
    $reflection->invoke( null );

    $aam_settings = FacebookWordpressOptions::get_aam_settings();

    // With no API available and mismatched cache, settings should be null.
    $this->assertNull( $aam_settings );
  }

  // =========================================
  // MBE & FBL4B Pixel Coexistence Tests
  // =========================================

  /**
   * Tests that when both MBE and FBL4B have pixel IDs configured,
   * the active pixel ID is the FBL4B one (FBL4B takes priority).
   */
  public function testActivePixelIdFBL4BTakesPriorityOverMBE() {
    $this->mockWpSalt();
    $encrypted_token = FacebookWordpressOptions::encrypt_token( 'test_token' );

    // get_option returns different values for different keys.
    \WP_Mock::userFunction(
      'get_option',
      array(
        'return_in_order' => array(
          // First call: FBL4B settings (for get_connection_type)
          array(
            FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => $encrypted_token,
            FacebookPluginConfig::FBL4B_PIXEL_ID_KEY     => '77777',
          ),
          // Second call: FBL4B settings again (for get_fbl4b_pixel_id)
          array(
            FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => $encrypted_token,
            FacebookPluginConfig::FBL4B_PIXEL_ID_KEY     => '77777',
          ),
        ),
      )
    );

    $active_pixel = FacebookWordpressOptions::get_active_pixel_id();
    $this->assertEquals( '77777', $active_pixel );
  }

  // =========================================
  // Agent String Tests
  // =========================================

  /**
   * Tests that agent string includes _fbl4b suffix when FBL4B is connected.
   */
  public function testAgentStringIncludesFBL4BSuffix() {
    $this->mockWpSalt();
    $encrypted_token = FacebookWordpressOptions::encrypt_token( 'test_token' );

    \WP_Mock::userFunction(
      'get_option',
      array(
        'return' => array(
          FacebookPluginConfig::FBL4B_ACCESS_TOKEN_KEY => $encrypted_token,
          FacebookPluginConfig::FBL4B_PIXEL_ID_KEY     => '12345',
        ),
      )
    );

    $GLOBALS['wp_version'] = '6.9';
    FacebookWordpressOptions::set_version_info();
    $agent_string = FacebookWordpressOptions::get_agent_string();

    $this->assertStringContainsString( '_fbl4b', $agent_string );
    $this->assertEquals(
      FacebookPluginConfig::SOURCE . '_fbl4b-6.9-' . FacebookPluginConfig::PLUGIN_VERSION,
      $agent_string
    );
  }

  /**
   * Tests that agent string does NOT include _fbl4b when using MBE.
   */
  public function testAgentStringExcludesFBL4BWhenMBE() {
    \WP_Mock::userFunction(
      'get_option',
      array( 'return' => array() )
    );

    $GLOBALS['wp_version'] = '6.9';
    FacebookWordpressOptions::set_version_info();
    $agent_string = FacebookWordpressOptions::get_agent_string();

    $this->assertStringNotContainsString( '_fbl4b', $agent_string );
    $this->assertEquals(
      FacebookPluginConfig::SOURCE . '-6.9-' . FacebookPluginConfig::PLUGIN_VERSION,
      $agent_string
    );
  }

  // =========================================
  // Helper Methods
  // =========================================

  /**
   * Mocks wp_salt('auth') to return a fixed test key.
   */
  private function mockWpSalt() {
    \WP_Mock::userFunction(
      'wp_salt',
      array(
        'args'   => array( 'auth' ),
        'return' => 'test-salt-key-for-encryption-do-not-use-in-prod',
      )
    );
  }
}
