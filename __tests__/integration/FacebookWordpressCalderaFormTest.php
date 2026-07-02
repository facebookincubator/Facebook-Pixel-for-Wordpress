<?php
/**
 * Facebook Pixel Plugin FacebookWordpressCalderaFormTest class.
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
use FacebookPixelPlugin\Core\AjaxHtmlDelivery;
use FacebookPixelPlugin\Integration\FacebookWordpressCalderaForm;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressCalderaFormTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressCalderaFormTest extends FacebookWordpressTestBase {

  /**
   * Builds a Caldera integration with a mock Signals + real builder.
   *
   * @return array [ FacebookWordpressCalderaForm, Signals mock ]
   */
  private function makeIntegration() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressCalderaForm(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * A Caldera form fixture whose fields map to $_POST values.
   *
   * @return array
   */
  private function form() {
    $_POST['fld1'] = 'pika.chu@s2s.com';
    $_POST['fld2'] = 'Pika';
    $_POST['fld3'] = 'Chu';
    $_POST['fld4'] = '123456';
    $_POST['fld5'] = 'Ohio';
    return array(
      'ID'     => 'cf1',
      'fields' => array(
        array( 'ID' => 'fld1', 'type' => 'email' ),
        array( 'ID' => 'fld2', 'slug' => 'first_name' ),
        array( 'ID' => 'fld3', 'slug' => 'last_name' ),
        array( 'ID' => 'fld4', 'type' => 'phone' ),
        array( 'ID' => 'fld5', 'type' => 'states' ),
      ),
    );
  }

  /**
   * set_up_tracking registers the Lead event with an AjaxHtmlDelivery.
   *
   * @return void
   */
  public function testInjectPixelCodeRegistersEvent() {
    list( $integration, $signals ) = $this->makeIntegration();

    $signals->expects( $this->once() )
      ->method( 'on' )
      ->with(
        'caldera_forms_ajax_return',
        'Lead',
        $this->isType( 'callable' ),
        $this->isInstanceOf( AjaxHtmlDelivery::class ),
        'caldera-forms',
        10,
        2
      );

    $integration->set_up_tracking();

    $this->assertConditionsMet();
  }

  /**
   * read_form_data extracts $_POST values for a completed submission.
   *
   * @return void
   */
  public function testReadFormDataReturnsEventData() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    list( $integration ) = $this->makeIntegration();

    $data = $integration->read_form_data(
      array( 'status' => 'complete' ),
      $this->form()
    );

    $this->assertInstanceOf( EventData::class, $data );
    $this->assertEquals(
      array(
        'email'      => 'pika.chu@s2s.com',
        'first_name' => 'Pika',
        'last_name'  => 'Chu',
        'phone'      => '123456',
        'state'      => 'Ohio',
      ),
      $data->to_array()
    );
  }

  /**
   * read_form_data returns null when the submission is not complete.
   *
   * @return void
   */
  public function testReadFormDataSkipsWhenNotComplete() {
    list( $integration ) = $this->makeIntegration();

    $this->assertNull(
      $integration->read_form_data(
        array( 'status' => 'preprocess' ),
        $this->form()
      )
    );
  }
}
