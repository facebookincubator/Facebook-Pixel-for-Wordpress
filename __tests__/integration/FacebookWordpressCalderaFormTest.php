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
use FacebookPixelPlugin\Core\EventDataBuilder;
use FacebookPixelPlugin\Core\AjaxHtmlDelivery;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Integration\FacebookWordpressCalderaForm;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressCalderaFormTest class.
 *
 * These tests assert the same observable behavior as before the Signals
 * refactor: a completed Caldera submission tracks a Lead server-side event
 * carrying the correct user data. The only change is the entry point — the
 * read_form_data data provider plus the real Signals dispatch path instead of
 * the old injectLeadEvent static method.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressCalderaFormTest extends FacebookWordpressTestBase {

  /**
   * Builds a Caldera integration wired to a real Signals dispatcher, so a
   * dispatched event actually tracks through FacebookServerSideEvent.
   *
   * @return array [ FacebookWordpressCalderaForm, Signals ]
   */
  private function makeIntegration() {
    $signals     = new Signals();
    $integration = new FacebookWordpressCalderaForm(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * Stubs the JSON/sanitize helpers the dispatch/read path relies on.
   *
   * @return void
   */
  private function mockRenderHelpers() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );
    \WP_Mock::userFunction(
      'wp_json_encode',
      array(
        'args'   => array( \Mockery::type( 'array' ), \Mockery::type( 'int' ) ),
        'return' => function ( $data, $options ) {
          return json_encode( $data );
        },
      )
    );
  }

  /**
   * Builds a mock Caldera form definition and populates the matching $_POST
   * field values, mirroring a completed submission.
   *
   * @return array The Caldera form data array.
   */
  private static function createMockForm() {
    $email_field = array(
      'ID'   => 'fld_1',
      'type' => 'email',
    );

    $first_name_field = array(
      'ID'   => 'fld_2',
      'slug' => 'first_name',
    );

    $last_name_field = array(
      'ID'   => 'fld_3',
      'slug' => 'last_name',
    );

    $phone = array(
      'ID'   => 'fld_4',
      'type' => 'phone',
    );

    $state_field = array(
      'ID'   => 'fld_5',
      'type' => 'states',
    );

    $_POST['fld_1'] = 'pika.chu@s2s.com';
    $_POST['fld_2'] = 'Pika';
    $_POST['fld_3'] = 'Chu';
    $_POST['fld_4'] = '(206)123-4567';
    $_POST['fld_5'] = 'WA';

    return array(
      'fields' => array(
        $email_field,
        $first_name_field,
        $last_name_field,
        $phone,
        $state_field,
      ),
    );
  }

  /**
   * set_up_tracking registers the Lead Signals event on the Caldera AJAX
   * return hook, using the AJAX HTML delivery.
   *
   * @return void
   */
  public function testSetUpTrackingRegistersLeadSignal() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressCalderaForm(
      $signals,
      new EventDataBuilder()
    );

    $delivery = null;
    $signals->expects( $this->once() )
      ->method( 'on' )
      ->willReturnCallback(
        function ( $hook, $event, $provider, $del ) use ( &$delivery ) {
          $this->assertEquals( 'caldera_forms_ajax_return', $hook );
          $this->assertEquals( 'Lead', $event );
          $delivery = $del;
        }
      );

    $integration->set_up_tracking();

    $this->assertInstanceOf( AjaxHtmlDelivery::class, $delivery );
  }

  /**
   * A completed submission tracks a Lead server event with the submitter's
   * user data, dispatched through the real Signals path.
   *
   * @return void
   */
  public function testLeadEventWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $this->mockRenderHelpers();

    $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

    $out  = array(
      'status' => 'complete',
      'html'   => 'successful submitted',
    );
    $form = self::createMockForm();

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_form_data( $out, $form );
    $signals->dispatch(
      'Lead',
      $data,
      FacebookWordpressCalderaForm::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'Lead', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );

    $user_data = $event->getUserData();
    $this->assertEquals( 'pika.chu@s2s.com', $user_data->getEmail() );
    $this->assertEquals( 'pika', $user_data->getFirstName() );
    $this->assertEquals( 'chu', $user_data->getLastName() );
    $this->assertEquals( '2061234567', $user_data->getPhone() );
    $this->assertEquals( 'wa', $user_data->getState() );

    $this->assertEquals(
      'caldera-forms',
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );
    $this->assertEquals( 'TEST_REFERER', $event->getEventSourceUrl() );
  }

  /**
   * read_form_data returns null (nothing to dispatch) when the submission is
   * not complete, so no server event is tracked.
   *
   * @return void
   */
  public function testReadFormDataReturnsNullWhenNotComplete() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();

    $out  = array(
      'status' => 'preprocess',
      'html'   => 'fail to submit form',
    );
    $form = self::createMockForm();

    list( $integration ) = $this->makeIntegration();

    $this->assertNull( $integration->read_form_data( $out, $form ) );

    $this->assertCount(
      0,
      FacebookServerSideEvent::get_instance()->get_tracked_events()
    );
  }

  /**
   * read_form_data returns null when the form data is empty, even for a
   * completed submission.
   *
   * @return void
   */
  public function testReadFormDataReturnsNullWhenFormEmpty() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();

    $out = array(
      'status' => 'complete',
      'html'   => 'successful submitted',
    );

    list( $integration ) = $this->makeIntegration();

    $this->assertNull( $integration->read_form_data( $out, array() ) );

    $this->assertCount(
      0,
      FacebookServerSideEvent::get_instance()->get_tracked_events()
    );
  }
}
