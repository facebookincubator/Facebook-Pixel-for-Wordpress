<?php
/**
 * Facebook Pixel Plugin FacebookWordpressContactForm7Test class.
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
use FacebookPixelPlugin\Integration\FacebookWordpressContactForm7;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressContactForm7Test class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressContactForm7Test extends FacebookWordpressTestBase {

  /**
   * Builds a CF7 integration with a mock Signals and a real builder.
   *
   * @return array [ FacebookWordpressContactForm7, Signals mock ]
   */
  private function makeIntegration() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressContactForm7(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * inject_pixel_code registers the Lead event on Signals (with the AJAX
   * delivery) and the front-end mail-sent listener.
   *
   * @return void
   */
  public function testInjectPixelCodeRegistersEventAndListener() {
    list( $integration, $signals ) = $this->makeIntegration();

    $signals->expects( $this->once() )
      ->method( 'on' )
      ->with(
        'wpcf7_submit',
        'Lead',
        $this->isType( 'callable' ),
        $this->isInstanceOf( AjaxFilterDelivery::class ),
        'contact-form-7',
        10,
        2
      );

    \WP_Mock::expectActionAdded(
      'wp_footer',
      array( $integration, 'inject_mail_sent_listener' ),
      10,
      2
    );

    $integration->inject_pixel_code();

    $this->assertConditionsMet();
  }

  /**
   * read_form_data extracts the standard fields into an EventData.
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
    $form                = $this->createMockForm();

    $data = $integration->read_form_data(
      $form,
      array( 'status' => 'mail_sent' )
    );

    $this->assertInstanceOf( EventData::class, $data );
    $this->assertEquals(
      array(
        'email'      => 'pika.chu@s2s.com',
        'first_name' => 'Pika',
        'last_name'  => 'Chu',
        'phone'      => '12223334444',
      ),
      $data->to_array()
    );
  }

  /**
   * read_form_data returns null (no dispatch) when the mail did not send.
   *
   * @return void
   */
  public function testReadFormDataSkipsWhenNotMailSent() {
    list( $integration ) = $this->makeIntegration();
    $form                = $this->createMockForm();

    $this->assertNull(
      $integration->read_form_data( $form, array( 'status' => 'spam' ) )
    );
  }

  /**
   * Creates a mock CF7 form with sample email/name/phone tags (which also
   * populate $_POST).
   *
   * @return MockContactForm7
   */
  private function createMockForm() {
    $mock_form = new MockContactForm7();
    $mock_form->add_tag( 'email', 'your-email', 'pika.chu@s2s.com' );
    $mock_form->add_tag( 'text', 'your-name', 'Pika Chu' );
    $mock_form->add_tag( 'tel', 'your-phone-number', '12223334444' );
    return $mock_form;
  }
}
