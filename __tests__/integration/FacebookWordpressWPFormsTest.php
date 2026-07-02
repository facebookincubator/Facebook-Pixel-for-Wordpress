<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWPFormsTest class.
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
use FacebookPixelPlugin\Core\CompositeDelivery;
use FacebookPixelPlugin\Integration\FacebookWordpressWPForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressWPFormsTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressWPFormsTest extends FacebookWordpressTestBase {

  /**
   * Builds a WPForms integration with a mock Signals and a real builder.
   *
   * @return array [ FacebookWordpressWPForms, Signals mock ]
   */
  private function makeIntegration() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressWPForms(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * set_up_tracking registers the Lead event (with a composite footer+AJAX
   * delivery) and the front-end AJAX listener.
   *
   * @return void
   */
  public function testInjectPixelCodeRegistersEventAndListener() {
    list( $integration, $signals ) = $this->makeIntegration();

    $signals->expects( $this->once() )
      ->method( 'on' )
      ->with(
        'wpforms_process_before',
        'Lead',
        $this->isType( 'callable' ),
        $this->isInstanceOf( CompositeDelivery::class ),
        'wpforms-lite',
        20,
        2
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
   * read_form_data extracts the standard fields into an EventData.
   *
   * @return void
   */
  public function testReadFormDataReturnsEventData() {
    list( $integration ) = $this->makeIntegration();

    $form_data = array(
      'fields' => array(
        array(
          'type'   => 'name',
          'format' => 'simple',
          'id'     => 0,
        ),
        array(
          'type' => 'email',
          'id'   => 1,
        ),
      ),
    );
    $entry     = array(
      'fields' => array(
        0 => 'Pika Chu',
        1 => 'pika.chu@s2s.com',
      ),
    );

    $data = $integration->read_form_data( $entry, $form_data );

    $this->assertInstanceOf( EventData::class, $data );
    $this->assertEquals(
      array(
        'email'      => 'pika.chu@s2s.com',
        'first_name' => 'Pika',
        'last_name'  => 'Chu',
      ),
      $data->to_array()
    );
  }

  /**
   * read_form_data returns null when the entry or form data is empty.
   *
   * @return void
   */
  public function testReadFormDataSkipsWhenEmpty() {
    list( $integration ) = $this->makeIntegration();

    $this->assertNull( $integration->read_form_data( array(), array() ) );
  }
}
