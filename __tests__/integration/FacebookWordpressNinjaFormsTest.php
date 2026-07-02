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
use FacebookPixelPlugin\Core\EventDataBuilder;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Integration\FacebookWordpressNinjaForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressNinjaFormsTest class.
 *
 * These tests assert the same observable behavior as before the Signals
 * refactor: a Ninja Forms submission that carries a success-message action
 * tracks a Lead server-side event with the correct user data. The only change
 * is the entry point — the read_form_data provider plus the real Signals
 * dispatch path instead of the old injectLeadEvent static method.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressNinjaFormsTest extends FacebookWordpressTestBase {

  /**
   * Builds a Ninja Forms integration wired to a real Signals dispatcher, so a
   * dispatched event actually tracks through FacebookServerSideEvent.
   *
   * @return array [ FacebookWordpressNinjaForms, Signals ]
   */
  private function makeIntegration() {
    $signals     = new Signals();
    $integration = new FacebookWordpressNinjaForms(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * Mocks the current user, plugin options, and the sanitize helper the
   * dispatch path relies on.
   *
   * @return void
   */
  private function setupUserAndOptions() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();

    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'args'   => array( \Mockery::any() ),
        'return' => function ( $input ) {
          return $input;
        },
      )
    );
  }

  /**
   * A submission with a success-message action feeds the form fields through
   * read_form_data and dispatches a Lead event carrying all the user data —
   * same observable outcome as the old injectLeadEvent path.
   *
   * @return void
   */
  public function testSubmissionTracksLeadEventWithoutInternalUser() {
    $this->setupUserAndOptions();

    $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

    $actions = array(
      array(
        'id'       => 1,
        'settings' => array(
          'type'        => 'successmessage',
          'success_msg' => 'successful',
        ),
      ),
    );

    list( $integration, $signals ) = $this->makeIntegration();

    $data = $integration->read_form_data(
      $actions,
      null,
      $this->getMockFormData()
    );
    $this->assertNotNull( $data );

    $signals->dispatch(
      'Lead',
      $data,
      FacebookWordpressNinjaForms::TRACKING_NAME
    );

    $tracked_events =
      FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked_events );

    $event     = $tracked_events[0];
    $user_data = $event->getUserData();
    $this->assertEquals( 'Lead', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertEquals( 'pika.chu@s2s.com', $user_data->getEmail() );
    $this->assertEquals( 'pika', $user_data->getFirstName() );
    $this->assertEquals( 'chu', $user_data->getLastName() );
    $this->assertEquals( '12345', $user_data->getPhone() );
    $this->assertEquals( 'oh', $user_data->getState() );
    $this->assertEquals( 'springfield', $user_data->getCity() );
    $this->assertEquals( 'us', $user_data->getCountryCode() );
    $this->assertEquals( '4321', $user_data->getZipCode() );
    $this->assertEquals( 'm', $user_data->getGender() );
    $this->assertEquals(
      'ninja-forms',
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );
    $this->assertEquals( 'TEST_REFERER', $event->getEventSourceUrl() );
  }

  /**
   * read_form_data returns null (nothing to dispatch) when the submission has
   * no success-message action.
   *
   * @return void
   */
  public function testReadFormDataSkipsWithoutSuccessMessage() {
    $this->setupUserAndOptions();

    $actions = array(
      array(
        'id'       => 1,
        'settings' => array( 'type' => 'email' ),
      ),
    );

    list( $integration ) = $this->makeIntegration();

    $this->assertNull(
      $integration->read_form_data( $actions, null, $this->getMockFormData() )
    );
  }

  /**
   * read_form_data returns null when the submission carries no form data.
   *
   * @return void
   */
  public function testReadFormDataSkipsWithoutFormData() {
    $this->setupUserAndOptions();

    $actions = array(
      array(
        'id'       => 1,
        'settings' => array( 'type' => 'successmessage' ),
      ),
    );

    list( $integration ) = $this->makeIntegration();

    $this->assertNull(
      $integration->read_form_data( $actions, null, array() )
    );
  }

  /**
   * set_up_tracking registers the Lead event through Signals and adds the
   * front-end AJAX listener hook.
   *
   * @return void
   */
  public function testSetUpTrackingRegistersLeadSignal() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressNinjaForms(
      $signals,
      new EventDataBuilder()
    );

    $signals->expects( $this->once() )
      ->method( 'on' )
      ->with(
        'ninja_forms_submission_actions',
        'Lead',
        array( $integration, 'read_form_data' )
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
   * The AJAX response listener script is printed on wp_footer.
   *
   * @return void
   */
  public function testInjectAjaxListenerOutputsScript() {
    list( $integration ) = $this->makeIntegration();

    $integration->inject_ajax_listener();
    $this->expectOutputRegex( '/submit:response/' );
  }

  /**
   * Creates a mock Ninja Forms submission with some sample form fields.
   *
   * @return array
   */
  private function getMockFormData() {
    $fields = array(
      array(
        'key'   => 'email',
        'value' => 'pika.chu@s2s.com',
      ),
      array(
        'key'   => 'name',
        'value' => 'Pika Chu',
      ),
      array(
        'key'   => 'phone',
        'value' => '12345',
      ),
      array(
        'key'   => 'city',
        'value' => 'Springfield',
      ),
      array(
        'key'   => 'liststate',
        'value' => 'OH',
      ),
      array(
        'key'   => 'listcountry',
        'value' => 'US',
      ),
      array(
        'key'   => 'zip',
        'value' => '4321',
      ),
      array(
        'key'   => 'gender',
        'value' => 'M',
      ),
    );
    return array( 'fields' => $fields );
  }
}
