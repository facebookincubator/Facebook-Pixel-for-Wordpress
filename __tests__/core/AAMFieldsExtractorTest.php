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

use FacebookPixelPlugin\FacebookAds\Object\ServerSide\AdsPixelSettings;

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
   * returns the expected normalized data.
   *
   * @return void
   */
  public function testReturnsNormalizedData() {
    $user_data_normalized =
    AAMFieldsExtractor::get_normalized_user_data( $this->getSampleUserData() );

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
   * The pixel's automatic advanced matching setting does not gate the
   * parameters the plugin captures: with it off, every field is returned.
   *
   * @return void
   */
  public function testReturnsAllFieldsWhenAamDisabled() {
    $this->mockUseAAM( '1234', false );
    $this->assertSameSize(
      AAMSettingsFields::get_all_fields(),
      AAMFieldsExtractor::get_normalized_user_data( $this->getSampleUserData() )
    );
  }

  /**
   * The types turned on in the pixel's automatic advanced matching setting
   * do not limit the fields returned.
   *
   * @return void
   */
  public function testIgnoresAamEnabledFields() {
    $this->mockUseAAM(
      '1234',
      true,
      array( AAMSettingsFields::EMAIL )
    );
    $user_data_normalized =
      AAMFieldsExtractor::get_normalized_user_data( $this->getSampleUserData() );
    $this->assertSameSize(
      AAMSettingsFields::get_all_fields(),
      $user_data_normalized
    );
    $this->assertEquals(
      'pedro',
      $user_data_normalized[ AAMSettingsFields::FIRST_NAME ]
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
      AAMFieldsExtractor::get_normalized_user_data(
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
      AAMFieldsExtractor::get_normalized_user_data( array() )
    );
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
