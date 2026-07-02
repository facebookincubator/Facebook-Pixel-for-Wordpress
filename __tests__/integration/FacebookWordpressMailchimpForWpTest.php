<?php
/**
 * Facebook Pixel Plugin FacebookWordpressMailchimpForWpTest class.
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
use FacebookPixelPlugin\Integration\FacebookWordpressMailchimpForWp;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressMailchimpForWpTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressMailchimpForWpTest
  extends FacebookWordpressTestBase {

  /**
   * Builds a Mailchimp integration with a mock Signals + real builder.
   *
   * @return array [ FacebookWordpressMailchimpForWp, Signals mock ]
   */
  private function makeIntegration() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressMailchimpForWp(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * inject_pixel_code registers the Lead event with a footer delivery.
   *
   * @return void
   */
  public function testInjectPixelCodeRegistersEvent() {
    list( $integration, $signals ) = $this->makeIntegration();

    $signals->expects( $this->once() )
      ->method( 'on' )
      ->with(
        'mc4wp_form_subscribed',
        'Lead',
        $this->isType( 'callable' ),
        $this->isInstanceOf( FooterDelivery::class ),
        'mailchimp-for-wp',
        10,
        1
      );

    $integration->inject_pixel_code();

    $this->assertConditionsMet();
  }

  /**
   * read_form_data extracts the $_POST subscribe fields into an EventData.
   *
   * @return void
   */
  public function testReadFormDataReturnsEventData() {
    \WP_Mock::userFunction(
      'sanitize_email',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    $_POST['EMAIL'] = 'pika.chu@s2s.com';
    $_POST['FNAME'] = 'Pika';
    $_POST['LNAME'] = 'Chu';
    $_POST['PHONE'] = '123456';

    list( $integration ) = $this->makeIntegration();

    $data = $integration->read_form_data();

    $this->assertInstanceOf( EventData::class, $data );
    $this->assertEquals(
      array(
        'email'      => 'pika.chu@s2s.com',
        'first_name' => 'Pika',
        'last_name'  => 'Chu',
        'phone'      => '123456',
      ),
      $data->to_array()
    );
  }
}
