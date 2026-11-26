<?php
/**
 * Facebook Pixel Plugin UserDataNormalizerTest class.
 *
 * This file contains the main logic for UserDataNormalizerTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define UserDataNormalizerTest class.
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
use FacebookPixelPlugin\Core\UserDataNormalizer;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * UserDataNormalizerTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class UserDataNormalizerTest extends FacebookWordpressTestBase {
  /**
   * Every user data field is normalized: lowercased and trimmed, phone
   * reduced to digits, gender to its first letter, date of birth to Ymd.
   *
   * @return void
   */
  public function testReturnsNormalizedData() {
    $user_data_normalized =
    UserDataNormalizer::normalize( $this->getSampleUserData() );

    $this->assertEquals(
      array(
        AAMSettingsFields::EMAIL         => 'abc@mail.com',
        AAMSettingsFields::LAST_NAME     => 'perez',
        AAMSettingsFields::FIRST_NAME    => 'pedro',
        AAMSettingsFields::PHONE         => '567891234',
        AAMSettingsFields::GENDER        => 'm',
        AAMSettingsFields::EXTERNAL_ID   => '1',
        AAMSettingsFields::COUNTRY       => 'us',
        AAMSettingsFields::CITY          => 'seattle',
        AAMSettingsFields::STATE         => 'wa',
        AAMSettingsFields::ZIP_CODE      => '12345',
        AAMSettingsFields::DATE_OF_BIRTH => '19900611',
      ),
      $user_data_normalized
    );
  }

  /**
   * Keys that are not user data fields are dropped.
   *
   * @return void
   */
  public function testDropsUnknownKeys() {
    $this->assertEquals(
      array( AAMSettingsFields::EMAIL => 'abc@mail.com' ),
      UserDataNormalizer::normalize(
        array(
          AAMSettingsFields::EMAIL => 'ABC@mail.com',
          'value'                  => '10',
        )
      )
    );
  }

  /**
   * Empty input gives an empty array.
   *
   * @return void
   */
  public function testReturnsEmptyArrayForEmptyInput() {
    $this->assertSame(
      array(),
      UserDataNormalizer::normalize( array() )
    );
  }

  /**
   * Returns a sample user data array.
   *
   * This function returns a sample user data
   * array with every user data field.
   *
   * @return array The sample user data array.
   */
  private function getSampleUserData() {
    return array(
      AAMSettingsFields::EMAIL         => 'abc@mail.com',
      AAMSettingsFields::LAST_NAME     => 'Perez',
      AAMSettingsFields::FIRST_NAME    => 'Pedro',
      AAMSettingsFields::PHONE         => '567-891-234',
      AAMSettingsFields::GENDER        => 'Male',
      AAMSettingsFields::EXTERNAL_ID   => '1',
      AAMSettingsFields::COUNTRY       => 'US',
      AAMSettingsFields::CITY          => 'Seattle',
      AAMSettingsFields::STATE         => 'WA',
      AAMSettingsFields::ZIP_CODE      => '12345',
      AAMSettingsFields::DATE_OF_BIRTH => '1990-06-11',
    );
  }
}
