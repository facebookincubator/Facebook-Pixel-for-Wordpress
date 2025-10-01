<?php
/**
 * Facebook Pixel Plugin AAMFieldsExtractorTest class.
 *
 * This file contains the main logic for AAMFieldsExtractorTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define AAMFieldsExtractorTest class.
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

use FacebookPixelPlugin\FacebookAdsObject\ServerSide\AdsPixelSettings;

use FacebookPixelPlugin\Core\AAMSettingsFields;
use FacebookPixelPlugin\Core\AAMFieldsExtractor;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * AAMFieldsExtractorTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class AAMFieldsExtractorTest extends FacebookWordpressTestBase {
  /**
   * Test that get_normalized_user_data
   * returns the expected normalized data when AAM is enabled.
   *
   * @return void
   */
  public function testReturnsNormalizedDataWhenAAMEnabled() {
    $this->mockUseAAM( '1234', true, AAMSettingsFields::get_all_fields() );
    $user_data_array      =
    $this->getSampleUserData();
    $user_data_normalized =
    AAMFieldsExtractor::get_normalized_user_data( $user_data_array );

    $this->assertEquals(
      'abc@mail.com',
      $user_data_normalized[ AAMSettingsFields::EMAIL ]
    );
    $this->assertEquals(
      'perez',
      $user_data_normalized[ AAMSettingsFields::LAST_NAME ]
    );
    $this->assertEquals(
      'pedro',
      $user_data_normalized[ AAMSettingsFields::FIRST_NAME ]
    );
    $this->assertEquals(
      '567891234',
      $user_data_normalized[ AAMSettingsFields::PHONE ]
    );
    $this->assertEquals(
      'm',
      $user_data_normalized[ AAMSettingsFields::GENDER ]
    );
    $this->assertEquals(
      '1',
      $user_data_normalized[ AAMSettingsFields::EXTERNAL_ID ]
    );
    $this->assertEquals(
      'us',
      $user_data_normalized[ AAMSettingsFields::COUNTRY ]
    );
    $this->assertEquals(
      'seattle',
      $user_data_normalized[ AAMSettingsFields::CITY ]
    );
    $this->assertEquals(
      'wa',
      $user_data_normalized[ AAMSettingsFields::STATE ]
    );
    $this->assertEquals(
      '12345',
      $user_data_normalized[ AAMSettingsFields::ZIP_CODE ]
    );
    $this->assertEquals(
      '19900611',
      $user_data_normalized[ AAMSettingsFields::DATE_OF_BIRTH ]
    );
  }

  /**
   * Verifies that get_normalized_user_data() returns
   * an array with only the fields enabled in the AAM settings.
   *
   * This test runs 25 times, with each iteration
   * testing a different subset of fields.
   * The subset of fields is randomly generated and
   * used to set the enabled fields in the AAM settings.
   * The test then verifies that the returned array
   * only contains the fields that were enabled in the AAM settings.
   */
  public function testReturnsArrayWithRequestedUserDataWhenAamEnabled() {
    $possible_fields = AAMSettingsFields::get_all_fields();
    $aam_settings    = $this->mockUseAAM( '1234', true );
    $user_data_array = $this->getSampleUserData();
    for ( $i = 0; $i < 25; ++$i ) {
      $fields_subset = $this->createSubset( $possible_fields );
      $aam_settings->setEnabledAutomaticMatchingFields( $fields_subset );
      $user_data_array_normalized =
        AAMFieldsExtractor::get_normalized_user_data( $user_data_array );
      $this->assertOnlyRequestedFieldsPresentInUserDataArray(
        $fields_subset,
        $user_data_array_normalized
      );
    }
  }

  /**
   * Tests that get_normalized_user_data
   * returns an empty array when AAM is disabled.
   *
   * This test verifies that when AAM is
   * disabled, the normalized user data array is empty.
   *
   * @return void
   */
  public function testReturnsEmptyArrayWhenAamDisabled() {
    $user_data_array = $this->getSampleUserData();
    $this->mockUseAAM( '1234', false );
    $user_data_array_normalized =
      AAMFieldsExtractor::get_normalized_user_data( $user_data_array );
    $this->assertEmpty( $user_data_array_normalized );
  }

  /**
   * Test that get_normalized_user_data returns
   * an empty array when AAM is not present.
   *
   * This test verifies that when AAM is not
   * present in the AAM settings, the normalized user data array is empty.
   */
  public function testReturnsEmptyArrayWhenAamNotPresent() {
    $user_data_array            = $this->getSampleUserData();
    $user_data_array_normalized =
      AAMFieldsExtractor::get_normalized_user_data( $user_data_array );
    $this->assertEmpty( $user_data_array_normalized );
  }

  /**
   * Asserts that the user data array only contains
   * the fields specified in the fields subset.
   *
   * @param array $fields_subset The subset of fields to check.
   * @param array $user_data_array The array of user data to check.
   *
   * @return void
   */
  private function assertOnlyRequestedFieldsPresentInUserDataArray(
    $fields_subset,
    $user_data_array
  ) {
    $this->assertEquals(
      count( $fields_subset ),
      count( $user_data_array )
    );
    foreach ( $fields_subset as $field ) {
      $this->assertArrayHasKey( $field, $user_data_array );
    }
  }

  /**
   * Creates a random subset of the given fields.
   *
   * This is used by the
   * testReturnsArrayWithRequestedUserDataWhenAamEnabled test to
   * generate a random subset of the user data fields to check.
   *
   * @param array $fields The array of fields to create a subset of.
   *
   * @return array The subset of fields.
   */
  private function createSubset( $fields ) {
    shuffle( $fields );
    $rand_num = rand() % count( $fields );
    $subset   = array();
    for ( $i = 0; $i < $rand_num; ++$i ) {
      $subset[] = $fields[ $i ];
    }
    return $subset;
  }

  /**
   * Mocks the return value of FacebookWordpressOptions::get_aam_settings().
   *
   * @param string $pixel_id The pixel ID
   * to set in the AAM settings.
   * @param bool   $enable_aam Whether to enable automatic matching.
   * @param array  $enable_aam_fields The
   * fields to enable for automatic matching.
   *
   * @return AdsPixelSettings The mocked AdsPixelSettings object.
   */
  private function mockUseAAM(
    $pixel_id = '1234',
    $enable_aam = false,
    $enable_aam_fields = array()
  ) {
    $aam_settings = new AdsPixelSettings();
    $aam_settings->setPixelId( $pixel_id );
    $aam_settings->setEnableAutomaticMatching( $enable_aam );
    $aam_settings->setEnabledAutomaticMatchingFields( $enable_aam_fields );
    $this->mocked_options = \Mockery::mock(
      'alias:FacebookPixelPlugin\Core\FacebookWordpressOptions'
    );
    $this->mocked_options->shouldReceive( 'get_aam_settings' )
      ->andReturn( $aam_settings );
    return $aam_settings;
  }

  /**
   * Returns a sample user data array.
   *
   * This function returns a sample user data
   * array with all the fields that can be used in automatic matching.
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
