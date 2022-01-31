<?php
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

use FacebookAds\Object\ServerSide\AdsPixelSettings;

use FacebookPixelPlugin\Core\AAMSettingsFields;
use FacebookPixelPlugin\Core\AAMFieldsExtractor;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */

final class AAMFieldsExtractorTest extends FacebookWordpressTestBase {
  public function testReturnsNormalizedDataWhenAAMEnabled() {
    $this->mockUseAAM('1234', true, AAMSettingsFields::getAllFields());
    $user_data_array = $this->getSampleUserData();
    $user_data_normalized =
        AAMFieldsExtractor::getNormalizedUserData($user_data_array);
    $this->assertEquals('abc@mail.com',
        $user_data_normalized[AAMSettingsFields::EMAIL]);
    $this->assertEquals('perez',
        $user_data_normalized[AAMSettingsFields::LAST_NAME]);
    $this->assertEquals('pedro',
        $user_data_normalized[AAMSettingsFields::FIRST_NAME]);
    $this->assertEquals('567891234',
        $user_data_normalized[AAMSettingsFields::PHONE]);
    $this->assertEquals('m',
        $user_data_normalized[AAMSettingsFields::GENDER]);
    $this->assertEquals('1',
        $user_data_normalized[AAMSettingsFields::EXTERNAL_ID]);
    $this->assertEquals('us',
        $user_data_normalized[AAMSettingsFields::COUNTRY]);
    $this->assertEquals('seattle',
        $user_data_normalized[AAMSettingsFields::CITY]);
    $this->assertEquals('wa',
        $user_data_normalized[AAMSettingsFields::STATE]);
    $this->assertEquals('12345',
        $user_data_normalized[AAMSettingsFields::ZIP_CODE]);
    $this->assertEquals('19900611',
        $user_data_normalized[AAMSettingsFields::DATE_OF_BIRTH]);
  }

  public function testReturnsArrayWithRequestedUserDataWhenAamEnabled(){
    $possible_fields = AAMSettingsFields::getAllFields();
    $aam_settings =  $this->mockUseAAM('1234', true);
    $user_data_array = $this->getSampleUserData();
    for( $i = 0; $i<25; $i += 1 ){
      $fields_subset = $this->createSubset($possible_fields);
      $aam_settings->setEnabledAutomaticMatchingFields($fields_subset);
      $user_data_array_normalized =
        AAMFieldsExtractor::getNormalizedUserData($user_data_array);
      $this->assertOnlyRequestedFieldsPresentInUserDataArray($fields_subset,
        $user_data_array_normalized);
    }
  }

  public function testReturnsEmptyArrayWhenAamDisabled(){
    $user_data_array = $this->getSampleUserData();
    $this->mockUseAAM('1234', false);
    $user_data_array_normalized =
        AAMFieldsExtractor::getNormalizedUserData($user_data_array);
    $this->assertEmpty($user_data_array_normalized);
  }

  public function testReturnsEmptyArrayWhenAamNotPresent(){
    $user_data_array = $this->getSampleUserData();
    $user_data_array_normalized =
        AAMFieldsExtractor::getNormalizedUserData($user_data_array);
    $this->assertEmpty($user_data_array_normalized);
  }

  private function assertOnlyRequestedFieldsPresentInUserDataArray($fieldsSubset,
    $userDataArray){
    $this->assertEquals(count($fieldsSubset), count($userDataArray));
    foreach($fieldsSubset as $field){
      $this->assertArrayHasKey($field, $userDataArray);
    }
  }

  private function createSubset($fields){
    shuffle($fields);
    $randNum = rand()%count($fields);
    $subset = array();
    for( $i = 0; $i < $randNum; $i+=1 ){
      $subset[] = $fields[$i];
    }
    return $subset;
  }

  private function mockUseAAM($pixel_id = '1234', $enable_aam = false,
    $enable_aam_fields = []){
    $aam_settings = new AdsPixelSettings();
    $aam_settings->setPixelId($pixel_id);
    $aam_settings->setEnableAutomaticMatching($enable_aam);
    $aam_settings->setEnabledAutomaticMatchingFields($enable_aam_fields);
    $this->mocked_options = \Mockery::mock(
      'alias:FacebookPixelPlugin\Core\FacebookWordpressOptions');
    $this->mocked_options->shouldReceive('getAAMSettings')->andReturn($aam_settings);
    return $aam_settings;
  }

  private function getSampleUserData(){
      return array(
        AAMSettingsFields::EMAIL => 'abc@mail.com',
        AAMSettingsFields::LAST_NAME => 'Perez',
        AAMSettingsFields::FIRST_NAME => 'Pedro',
        AAMSettingsFields::PHONE => '567-891-234',
        AAMSettingsFields::GENDER => 'Male',
        AAMSettingsFields::EXTERNAL_ID => '1',
        AAMSettingsFields::COUNTRY => 'US',
        AAMSettingsFields::CITY => 'Seattle',
        AAMSettingsFields::STATE => 'WA',
        AAMSettingsFields::ZIP_CODE => '12345',
        AAMSettingsFields::DATE_OF_BIRTH => '1990-06-11',
    );
  }
}
