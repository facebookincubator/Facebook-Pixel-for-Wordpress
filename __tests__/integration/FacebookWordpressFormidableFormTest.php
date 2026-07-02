<?php
/**
 * Facebook Pixel Plugin FacebookWordpressFormidableFormTest class.
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

namespace FacebookPixelPlugin\Tests\Integration;

use FacebookPixelPlugin\Core\Signals;
use FacebookPixelPlugin\Core\EventData;
use FacebookPixelPlugin\Core\EventDataBuilder;
use FacebookPixelPlugin\Core\FooterDelivery;
use FacebookPixelPlugin\Integration\FacebookWordpressFormidableForm;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormEntryValues;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormFieldValue;
use FacebookPixelPlugin\Tests\Mocks\MockFormidableFormField;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressFormidableFormTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressFormidableFormTest
  extends FacebookWordpressTestBase {

  /**
   * Builds a Formidable integration with a mock Signals + real builder.
   *
   * @return array [ FacebookWordpressFormidableForm, Signals mock ]
   */
  private function makeIntegration() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressFormidableForm(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * set_up_tracking registers the Lead event with a footer delivery.
   *
   * @return void
   */
  public function testInjectPixelCodeRegistersEvent() {
    list( $integration, $signals ) = $this->makeIntegration();

    $signals->expects( $this->once() )
      ->method( 'on' )
      ->with(
        'frm_after_create_entry',
        'Lead',
        $this->isType( 'callable' ),
        $this->isInstanceOf( FooterDelivery::class ),
        'formidable-lite',
        20,
        2
      );

    $integration->set_up_tracking();

    $this->assertConditionsMet();
  }

  /**
   * read_form_data extracts the entry field values into an EventData.
   *
   * @return void
   */
  public function testReadFormDataReturnsEventData() {
    self::setupMockFormidableForm( 1 );
    list( $integration ) = $this->makeIntegration();

    $data = $integration->read_form_data( 1, 1 );

    $this->assertInstanceOf( EventData::class, $data );
    $this->assertEquals(
      array(
        'email'      => 'pika.chu@s2s.com',
        'first_name' => 'Pika',
        'last_name'  => 'Chu',
        'phone'      => '123456',
        'city'       => 'Springfield',
        'state'      => 'Ohio',
        'zip'        => '45501',
      ),
      $data->to_array()
    );
  }

  /**
   * read_form_data returns null for an empty entry id.
   *
   * @return void
   */
  public function testReadFormDataSkipsWhenNoEntry() {
    list( $integration ) = $this->makeIntegration();
    $this->assertNull( $integration->read_form_data( 0 ) );
  }

  /**
   * Aliases IntegrationUtils to return a fixture entry with sample fields.
   *
   * @param int $entry_id The entry id.
   * @return void
   */
  private static function setupMockFormidableForm( $entry_id ) {
    $email      = new MockFormidableFormFieldValue(
      new MockFormidableFormField( 'email', null, null ),
      'pika.chu@s2s.com'
    );
    $first_name = new MockFormidableFormFieldValue(
      new MockFormidableFormField( 'text', 'Name', 'First' ),
      'Pika'
    );
    $last_name  = new MockFormidableFormFieldValue(
      new MockFormidableFormField( 'text', 'Last', 'Last' ),
      'Chu'
    );
    $phone      = new MockFormidableFormFieldValue(
      new MockFormidableFormField( 'phone', null, null ),
      '123456'
    );
    $address    = new MockFormidableFormFieldValue(
      new MockFormidableFormField( 'address', null, null ),
      array(
        'city'    => 'Springfield',
        'state'   => 'Ohio',
        'zip'     => '45501',
        'country' => 'United States',
      )
    );

    $entry_values = new MockFormidableFormEntryValues(
      array( $email, $first_name, $last_name, $phone, $address )
    );

    $mock_utils = \Mockery::mock(
      'alias:FacebookPixelPlugin\Integration\IntegrationUtils'
    );
    $mock_utils->shouldReceive( 'get_formidable_forms_entry_values' )
      ->with( $entry_id )
      ->andReturn( $entry_values );
  }
}
