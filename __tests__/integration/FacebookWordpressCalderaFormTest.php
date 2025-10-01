<?php
/**
 * Facebook Pixel Plugin FacebookWordpressCalderaFormTest class.
 *
 * This file contains the main logic for FacebookWordpressCalderaFormTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressCalderaFormTest class.
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

namespace FacebookPixelPlugin\Tests\Integration;

use FacebookPixelPlugin\Integration\FacebookWordpressCalderaForm;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\FacebookAdsObject\ServerSide\Event;
use FacebookPixelPlugin\FacebookAdsObject\ServerSide\UserData;

/**
 * FacebookWordpressCalderaFormTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordpressCalderaFormTest extends FacebookWordpressTestBase {
  /**
   * Tests that the inject_pixel_code method adds the
   * correct hooks to WordPress
   * and that no events are tracked after calling the method.
   *
   * @return void
   */
  public function testInjectPixelCode() {
    \WP_Mock::expectActionAdded(
      'caldera_forms_ajax_return',
      array( FacebookWordpressCalderaForm::class, 'injectLeadEvent' ),
      10,
      2
    );

    FacebookWordpressCalderaForm::inject_pixel_code();
    $this->assertHooksAdded();

    $this->assertCount(
      0,
      FacebookServerSideEvent::get_instance()->get_tracked_events()
    );
  }

  /**
   * Tests the injectLeadEvent method for a non-internal user
   * when the form submission is complete.
   *
   * This test checks that the Pixel code is correctly
   * appended to the HTML output
   * when the form submission status is 'complete' and the
   * user is not an internal user.
   * It verifies that the output HTML contains the
   * expected Pixel code pattern.
   *
   * @return void
   */
  public function testInjectLeadEventWithoutInternalUserAndSubmitted() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $mock_out = array(
      'status' => 'complete',
      'html'   => 'successful submitted',
    );

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
        'args'   => array(
          \Mockery::type( 'array' ),
          \Mockery::type( 'int' ),
        ),
        'return' => function ( $data, $options ) {
          return json_encode( $data );
        },
            )
        );

    $out = FacebookWordpressCalderaForm::injectLeadEvent( $mock_out, null );

    $this->assertArrayHasKey( 'html', $out );
    $code = $out['html'];
    $this->assertMatchesRegularExpression(
      '/caldera-forms[\s\S]+End Meta Pixel Event Code/',
      $code
    );
  }

  /**
   * Tests the injectLeadEvent method for a non-internal user
     * when the form submission is not complete.
   *
   * This test checks that the Pixel code is not appended to the HTML output
   * when the form submission status is not 'complete'
     * and the user is not an internal user.
   * It verifies that the output HTML is not modified
     * and the server-side event tracking list is empty.
   *
   * @return void
   */
  public function testInjectLeadEventWithoutInternalUserAndNotSubmitted() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $mock_out  = array(
      'status' => 'preprocess',
      'html'   => 'fail to submit form',
    );
    $mock_form = array();

    $out = FacebookWordpressCalderaForm::injectLeadEvent(
            $mock_out,
            $mock_form
        );

    $this->assertArrayHasKey( 'html', $out );
    $code = $out['html'];
    $this->assertEquals( 'fail to submit form', $code );

    $this->assertCount(
      0,
      FacebookServerSideEvent::get_instance()->get_tracked_events()
    );
  }

  /**
   * Tests the injectLeadEvent method for an internal
     * user when the form submission is complete.
   *
   * This test verifies that no Pixel code is
     * appended to the HTML output
   * when the user is an internal user, even if the form
     * submission status is 'complete'.
   * It asserts that the output HTML
     * remains unchanged and that no events are tracked.
   *
   * @return void
   */
  public function testInjectLeadEventWithInternalUser() {
    self::mockIsInternalUser( true );
    self::mockFacebookWordpressOptions();
    $mock_out  = array(
      'status' => 'complete',
      'html'   => 'successful submitted',
    );
    $mock_form = array();

    $out = FacebookWordpressCalderaForm::injectLeadEvent(
            $mock_out,
            $mock_form
        );

    $this->assertArrayHasKey( 'html', $out );
    $code = $out['html'];
    $this->assertEquals( 'successful submitted', $code );

    $this->assertCount(
      0,
      FacebookServerSideEvent::get_instance()->get_tracked_events()
    );
  }

  /**
   * Tests the injectLeadEvent method when the form submission
     * is complete and the user is not an internal user.
   *
   * This test verifies that the Pixel code is appended to the HTML output
   * and that the server-side event is tracked with the correct parameters.
   */
  public function testSendLeadEventViaServerAPISuccessWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();

    $mock_out                = array(
      'status' => 'complete',
      'html'   => 'successful submitted',
    );
    $mock_form               = self::createMockForm();
    $_SERVER['HTTP_REFERER'] = 'TEST_REFERER';

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
            'wp_unslash',
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
        'args'   => array(
          \Mockery::type( 'array' ),
          \Mockery::type( 'int' ),
        ),
        'return' => function ( $data, $options ) {
          return json_encode( $data );
        },
            )
        );

    $out = FacebookWordpressCalderaForm::injectLeadEvent(
            $mock_out,
            $mock_form
        );

    $this->assertArrayHasKey( 'html', $out );
    $code = $out['html'];
    $this->assertMatchesRegularExpression(
      '/caldera-forms[\s\S]+End Meta Pixel Event Code/',
      $code
    );

    $tracked_events =
    FacebookServerSideEvent::get_instance()->get_tracked_events();

    $this->assertCount( 1, $tracked_events );

    $event = $tracked_events[0];
    $this->assertEquals( 'Lead', $event->getEventName() );
    $this->assertNotNull( $event->getEventTime() );
    $this->assertEquals(
            'pika.chu@s2s.com',
            $event->getUserData()->getEmail()
        );
    $this->assertEquals( 'pika', $event->getUserData()->getFirstName() );
    $this->assertEquals( 'chu', $event->getUserData()->getLastName() );
    $this->assertEquals( '2061234567', $event->getUserData()->getPhone() );
    $this->assertEquals( 'wa', $event->getUserData()->getState() );
    $this->assertEquals(
      'caldera-forms',
      $event->getCustomData()->getCustomProperty(
                'fb_integration_tracking'
            )
    );
    $this->assertEquals( 'TEST_REFERER', $event->getEventSourceUrl() );
  }

  /**
   * Tests the injectLeadEvent method when the form submission
     * is not complete and the user is not an internal user.
   *
   * This test verifies that no Pixel code is appended to the HTML output and
   * that no server-side event is tracked when the
     * form submission status is not 'complete'
   * and the user is not an internal user.
   *
   * @return void
   */
  public function testSendLeadEventViaServerAPIFailureWithoutInternalUser() {
    self::mockIsInternalUser( false );
    self::mockFacebookWordpressOptions();
    $mock_out  = array(
      'status' => 'preprocess',
      'html'   => 'fail to submit form',
    );
    $mock_form = array();

    $out = FacebookWordpressCalderaForm::injectLeadEvent(
            $mock_out,
            $mock_form
        );

    $this->assertArrayHasKey( 'html', $out );
    $code = $out['html'];
    $this->assertEquals( 'fail to submit form', $code );

    $this->assertCount(
      0,
      FacebookServerSideEvent::get_instance()->get_tracked_events()
    );
  }

  /**
   * Tests the injectLeadEvent method when the form
     * submission is complete and the user is an internal user.
   *
   * This test verifies that no Pixel code is appended to the HTML output and
   * that no server-side event is tracked when the
     * form submission status is 'complete'
   * and the user is an internal user.
   *
   * @return void
   */
  public function testSendLeadEventViaServerAPIFailureWithInternalUser() {
    self::mockIsInternalUser( true );
    self::mockFacebookWordpressOptions();
    $mock_out  = array(
      'status' => 'complete',
      'html'   => 'successful submitted',
    );
    $mock_form = array();

    $s2s_spy = \Mockery::spy( FacebookServerSideEvent::class );

    $out = FacebookWordpressCalderaForm::injectLeadEvent(
            $mock_out,
            $mock_form
        );

    $this->assertArrayHasKey( 'html', $out );
    $code = $out['html'];
    $this->assertEquals( 'successful submitted', $code );

    $this->assertCount(
      0,
      FacebookServerSideEvent::get_instance()->get_tracked_events()
    );
  }

  /**
   * Creates a mock form data array with email, first name,
     * last name, phone, and state fields populated.
   *
   * @return array a mock form data array
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
}
