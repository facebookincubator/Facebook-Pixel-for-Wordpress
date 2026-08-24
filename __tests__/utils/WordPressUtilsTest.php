<?php
/**
 * Facebook Pixel Plugin WordPressUtilsTest class.
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

namespace FacebookPixelPlugin\Tests\Utils;

use FacebookPixelPlugin\Utils\WordPressUtils;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * WordPressUtilsTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WordPressUtilsTest extends FacebookWordpressTestBase {

  /**
   * A guest (logged-out) user has an empty WP_User (empty email/name, ID 0).
   * get_user_info() must drop those empties so we never emit bogus PII such as
   * an external_id of "0" shared across all logged-out visitors.
   *
   * @return void
   */
  public function testGetUserInfoForGuestReturnsEmpty() {
    \WP_Mock::userFunction(
      'wp_get_current_user',
      array(
        'return' => (object) array(
          'user_email'     => '',
          'user_firstname' => '',
          'user_lastname'  => '',
          'ID'             => 0,
        ),
      )
    );
    \WP_Mock::userFunction(
      'get_current_user_id',
      array( 'return' => 0 )
    );

    $this->assertSame( array(), WordPressUtils::get_user_info() );
  }

  /**
   * A logged-in user yields email/name/id plus the billing meta fields.
   *
   * @return void
   */
  public function testGetUserInfoForLoggedInUser() {
    \WP_Mock::userFunction(
      'wp_get_current_user',
      array(
        'return' => (object) array(
          'user_email'     => 'ash@pallet.com',
          'user_firstname' => 'Ash',
          'user_lastname'  => 'Ketchum',
          'ID'             => 7,
        ),
      )
    );
    \WP_Mock::userFunction(
      'get_current_user_id',
      array( 'return' => 7 )
    );
    $meta = array(
      'billing_city'     => 'Pallet',
      'billing_postcode' => '00001',
      'billing_country'  => 'US',
      'billing_state'    => 'KT',
      'billing_phone'    => '5550100',
    );
    foreach ( $meta as $key => $value ) {
      \WP_Mock::userFunction(
        'get_user_meta',
        array(
          'args'   => array( 7, $key, true ),
          'return' => $value,
        )
      );
    }

    $this->assertEquals(
      array(
        'email'      => 'ash@pallet.com',
        'first_name' => 'Ash',
        'last_name'  => 'Ketchum',
        'id'         => 7,
        'city'       => 'Pallet',
        'zip'        => '00001',
        'country'    => 'US',
        'state'      => 'KT',
        'phone'      => '5550100',
      ),
      WordPressUtils::get_user_info()
    );
  }

  /**
   * A legacy admin-ajax.php submit is a background request.
   *
   * @return void
   */
  public function testIsAjaxOrRestRequestForAdminAjax() {
    \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => true ) );

    $this->assertTrue( WordPressUtils::is_ajax_or_rest_request() );
  }

  /**
   * A REST request is a background request too, even though wp_doing_ajax() is
   * false for it — this is the path modern Contact Form 7 submits over.
   *
   * @return void
   */
  public function testIsAjaxOrRestRequestForRestRequest() {
    \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => false ) );
    define( 'REST_REQUEST', true );

    $this->assertTrue( WordPressUtils::is_ajax_or_rest_request() );
  }

  /**
   * A plain front-end page render is neither.
   *
   * @return void
   */
  public function testIsAjaxOrRestRequestForPageRender() {
    \WP_Mock::userFunction( 'wp_doing_ajax', array( 'return' => false ) );

    $this->assertFalse( WordPressUtils::is_ajax_or_rest_request() );
  }
}
