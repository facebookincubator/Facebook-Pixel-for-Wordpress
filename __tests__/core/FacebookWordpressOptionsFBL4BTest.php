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
