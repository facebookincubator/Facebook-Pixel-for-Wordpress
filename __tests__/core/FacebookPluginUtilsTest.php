<?php
/**
 * Facebook Pixel Plugin FacebookPluginUtilsTest class.
 *
 * This file contains the main logic for FacebookPluginUtilsTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookPluginUtilsTest class.
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

use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookPluginUtilsTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookPluginUtilsTest extends FacebookWordpressTestBase {
  /**
   * Tests the is_positive_integer method.
   *
   * Verifies that the is_positive_integer method returns true for positive
   * integers and false for all other values.
   *
   * @return void
   */
  public function testWhenIsPositiveInteger() {
    $this->assertTrue( FacebookPluginUtils::is_positive_integer( '1' ) );
    $this->assertFalse( FacebookPluginUtils::is_positive_integer( '0' ) );
    $this->assertFalse( FacebookPluginUtils::is_positive_integer( '-1' ) );
  }

  /**
   * Tests the is_internal_user method when the user is external.
   *
   * Verifies that the is_internal_user method returns false when the user
   * is external, i.e. when the user does not have the 'edit_posts' capability
   * and does not have the 'upload_files' capability.
   *
   * @return void
   */
  public function testIsInternalUser_WhenUserIsExternal() {
    \WP_Mock::userFunction(
      'current_user_can',
      array(
        'times'  => 1,
        'args'   => 'edit_posts',
        'return' => false,
      )
    );
    \WP_Mock::userFunction(
      'current_user_can',
      array(
        'times'  => 1,
        'args'   => 'upload_files',
        'return' => false,
      )
    );
    $is_internal_user = FacebookPluginUtils::is_internal_user();

    $this->assertFalse( $is_internal_user );
  }

  /**
   * Tests the is_internal_user method when the user can edit posts.
   *
   * Verifies that the is_internal_user method returns true when the user
   * has the 'edit_posts' capability.
   *
   * @return void
   */
  public function testIsInternalUser_WhenUserCanEditPosts() {
    \WP_Mock::userFunction(
      'current_user_can',
      array(
        'times'  => 1,
        'args'   => 'edit_posts',
        'return' => true,
      )
    );
    $is_internal_user = FacebookPluginUtils::is_internal_user();

    $this->assertTrue( $is_internal_user );
  }

  /**
   * Tests the is_internal_user method when the user can upload files.
   *
   * Verifies that the is_internal_user method returns true when the user
   * has the 'upload_files' capability but does not have the 'edit_posts'
   * capability.
   *
   * @return void
   */
  public function testIsInternalUser_WhenUserCanUploadFiles() {
    \WP_Mock::userFunction(
      'current_user_can',
      array(
        'times'  => 1,
        'args'   => 'edit_posts',
        'return' => false,
      )
    );
    \WP_Mock::userFunction(
      'current_user_can',
      array(
        'times'  => 1,
        'args'   => 'upload_files',
        'return' => true,
      )
    );
    $is_internal_user = FacebookPluginUtils::is_internal_user();

    $this->assertTrue( $is_internal_user );
  }
}
