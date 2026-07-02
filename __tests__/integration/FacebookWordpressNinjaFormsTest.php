<?php
/**
 * Facebook Pixel Plugin FacebookWordpressNinjaFormsTest class.
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
use FacebookPixelPlugin\Core\AjaxFilterDelivery;
use FacebookPixelPlugin\Integration\FacebookWordpressNinjaForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressNinjaFormsTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressNinjaFormsTest extends FacebookWordpressTestBase {

  /**
   * Builds a Ninja Forms integration with a mock Signals + real builder.
   *
   * @return array [ FacebookWordpressNinjaForms, Signals mock ]
   */
  private function makeIntegration() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressNinjaForms(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * A form_data fixture with name + email fields.
   *
   * @return array
   */
  private function formData() {
    return array(
      'fields' => array(
        array( 'key' => 'name_1', 'value' => 'Pika Chu' ),
        array( 'key' => 'email_1', 'value' => 'pika.chu@s2s.com' ),
      ),
    );
  }

  /**
   * set_up_tracking registers the Lead event (AJAX delivery) + listener.
   *
   * @return void
   */
  public function testInjectPixelCodeRegistersEventAndListener() {
    list( $integration, $signals ) = $this->makeIntegration();

    $signals->expects( $this->once() )
      ->method( 'on' )
      ->with(
        'ninja_forms_submission_actions',
        'Lead',
        $this->isType( 'callable' ),
        $this->isInstanceOf( AjaxFilterDelivery::class ),
        'ninja-forms',
        10,
        3
      );

    \WP_Mock::expectActionAdded(
      'wp_footer',
      array( $integration, 'inject_ajax_listener' ),
      9
    );

    $integration->set_up_tracking();

    $this->assertConditionsMet();
  }

  /**
   * read_form_data extracts fields when a success-message action is present.
   *
   * @return void
   */
  public function testReadFormDataReturnsEventData() {
    list( $integration ) = $this->makeIntegration();

    $actions = array( array( 'settings' => array( 'type' => 'successmessage' ) ) );

    $data = $integration->read_form_data( $actions, array(), $this->formData() );

    $this->assertInstanceOf( EventData::class, $data );
    $this->assertEquals(
      array(
        'first_name' => 'Pika',
        'last_name'  => 'Chu',
        'email'      => 'pika.chu@s2s.com',
      ),
      $data->to_array()
    );
  }

  /**
   * read_form_data returns null without a success-message action.
   *
   * @return void
   */
  public function testReadFormDataSkipsWithoutSuccessMessage() {
    list( $integration ) = $this->makeIntegration();

    $actions = array( array( 'settings' => array( 'type' => 'redirect' ) ) );

    $this->assertNull(
      $integration->read_form_data( $actions, array(), $this->formData() )
    );
  }
}
