<?php
/**
 * Facebook Pixel Plugin UserPiiResolverTest class.
 *
 * @package FacebookPixelPlugin
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

use FacebookPixelPlugin\Core\FacebookParamBuilder;
use FacebookPixelPlugin\Core\FacebookSignalState;
use FacebookPixelPlugin\Core\UserPiiResolver;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * UserPiiResolverTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class UserPiiResolverTest extends FacebookWordpressTestBase {
  /**
   * Passthrough mock for sanitize_text_field / wp_unslash.
   *
   * @return void
   */
  private function mockPassthroughStringHelpers() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
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
  }

  public function testGetIpAddressWorksWithIpV4() {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '24.17.77.101';
    $this->mockPassthroughStringHelpers();

    $this->assertEquals( '24.17.77.101', UserPiiResolver::get_ip_address() );
  }

  public function testGetIpAddressWorksWithIpV6() {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '2120:10a:c191:401::5:7170';
    $this->mockPassthroughStringHelpers();

    $this->assertEquals(
      '2120:10a:c191:401::5:7170',
      UserPiiResolver::get_ip_address()
    );
  }

  public function testGetIpAddressTakesFirstFromList() {
    $_SERVER['HTTP_X_FORWARDED_FOR']
      = '2120:10a:c191:401::5:7170, 24.17.77.101';
    $this->mockPassthroughStringHelpers();

    $this->assertEquals(
      '2120:10a:c191:401::5:7170',
      UserPiiResolver::get_ip_address()
    );
  }

  public function testGetIpAddressHonorsPrecedence() {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '24.17.77.101';
    $_SERVER['REMOTE_ADDR']          = '24.17.77.100';
    $this->mockPassthroughStringHelpers();

    $this->assertEquals( '24.17.77.101', UserPiiResolver::get_ip_address() );
  }

  public function testGetIpAddressWithInvalidIpReturnsNull() {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = 'INVALID';
    $this->mockPassthroughStringHelpers();

    $this->assertNull( UserPiiResolver::get_ip_address() );
  }

  public function testGetHttpUserAgent() {
    $_SERVER['HTTP_USER_AGENT'] = 'HTTP_USER_AGENT_VALUE';
    $this->mockPassthroughStringHelpers();

    $this->assertEquals(
      'HTTP_USER_AGENT_VALUE',
      UserPiiResolver::get_http_user_agent()
    );
  }

  public function testFbclidExtractedFromUrlIfFbcNotFound() {
    $_GET['fbclid'] = 'fbclid_str';
    $this->mockPassthroughStringHelpers();

    $fbc = UserPiiResolver::get_fbc();

    $this->assertStringStartsWith( 'fb.1.', $fbc );
    $this->assertStringContainsString( 'fbclid_str', $fbc );
  }

  public function testGetFbcFromCookie() {
    $_COOKIE['_fbc'] = '_fbc_value';
    $this->mockPassthroughStringHelpers();

    $this->assertEquals( '_fbc_value', UserPiiResolver::get_fbc() );
  }

  public function testGetFbpFromCookie() {
    $_COOKIE['_fbp'] = '_fbp_value';
    $this->mockPassthroughStringHelpers();

    $fbp               = UserPiiResolver::get_fbp();
    $param_builder_fbp = FacebookParamBuilder::get_fbp();
    if ( ! empty( $param_builder_fbp ) ) {
      $this->assertEquals( $param_builder_fbp, $fbp );
    } else {
      $this->assertEquals( '_fbp_value', $fbp );
    }
  }

  public function testParamBuilderFbpTakesPriorityOverCookie() {
    $_COOKIE['_fbp'] = 'cookie_fbp_value';
    $this->mockPassthroughStringHelpers();

    $param_builder_fbp = FacebookParamBuilder::get_fbp();
    $fbp               = UserPiiResolver::get_fbp();

    if ( ! empty( $param_builder_fbp ) ) {
      $this->assertEquals( $param_builder_fbp, $fbp );
    } else {
      $this->assertEquals( 'cookie_fbp_value', $fbp );
    }
  }

  public function testParamBuilderFbcTakesPriorityOverCookie() {
    $_COOKIE['_fbc'] = 'fb.1.100.old_fbclid';
    $this->mockPassthroughStringHelpers();

    $param_builder_fbc = FacebookParamBuilder::get_fbc();
    $fbc               = UserPiiResolver::get_fbc();

    if ( ! empty( $param_builder_fbc ) ) {
      $this->assertEquals( $param_builder_fbc, $fbc );
    } else {
      $this->assertEquals( 'fb.1.100.old_fbclid', $fbc );
    }
  }

  public function testFbcReconstructedWhenCookieFbclidChanged() {
    $_COOKIE['_fbc'] = 'fb.1.100.old_fbclid';
    $_GET['fbclid']  = 'new_fbclid';
    $this->mockPassthroughStringHelpers();

    $fbc = UserPiiResolver::get_fbc();

    $this->assertNotNull( $fbc );
    $this->assertStringContainsString( 'new_fbclid', $fbc );
  }

  public function testFbcCookieUsedWhenFbclidUnchanged() {
    $_COOKIE['_fbc'] = 'fb.1.100.same_fbclid';
    $_GET['fbclid']  = 'same_fbclid';
    $this->mockPassthroughStringHelpers();

    $param_builder_fbc = FacebookParamBuilder::get_fbc();
    $fbc               = UserPiiResolver::get_fbc();

    if ( ! empty( $param_builder_fbc ) ) {
      $this->assertEquals( $param_builder_fbc, $fbc );
    } else {
      $this->assertEquals( 'fb.1.100.same_fbclid', $fbc );
    }
  }

  public function testFbcNotSavedToSessionWhenPaused() {
    $_GET['fbclid'] = 'test_fbclid';
    $_SESSION       = array();

    FacebookSignalState::hold();
    $this->mockPassthroughStringHelpers();

    UserPiiResolver::get_fbc();

    $this->assertArrayNotHasKey( '_fbc', $_SESSION );
  }

  public function testFbcSavedToSessionWhenNotPaused() {
    $_GET['fbclid'] = 'test_fbclid';
    $_SESSION       = array();
    $this->mockPassthroughStringHelpers();

    $fbc = UserPiiResolver::get_fbc();

    if ( ! empty( $fbc ) ) {
      $this->assertArrayHasKey( '_fbc', $_SESSION );
      $this->assertEquals( $fbc, $_SESSION['_fbc'] );
    } else {
      $this->assertArrayNotHasKey( '_fbc', $_SESSION );
    }
  }

  public function testFbcFallsBackToSession() {
    $_SESSION['_fbc'] = 'fb.1.999.session_fbclid';
    $this->mockPassthroughStringHelpers();

    $param_builder_fbc = FacebookParamBuilder::get_fbc();
    $fbc               = UserPiiResolver::get_fbc();

    if ( ! empty( $param_builder_fbc ) ) {
      $this->assertEquals( $param_builder_fbc, $fbc );
    } else {
      $this->assertEquals( 'fb.1.999.session_fbclid', $fbc );
    }
  }
}
