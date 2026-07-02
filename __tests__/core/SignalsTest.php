<?php
/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\Signals;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * SignalsTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SignalsTest extends FacebookWordpressTestBase {
  /**
   * Test tri-state cookie reads.
   *
   * @return void
   */
  public function testGetSignalsStateReadsCookieValues() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
    \WP_Mock::userFunction(
      'wp_unslash',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );

    $this->assertNull( Signals::get_signal_state() );
    $this->assertFalse( Signals::should_hold_signals() );

    $_COOKIE[ Signals::COOKIE_NAME ] = Signals::STATE_HELD;
    $this->assertSame(
      Signals::STATE_HELD,
      Signals::get_signal_state()
    );
    $this->assertTrue( Signals::should_hold_signals() );

    $_COOKIE[ Signals::COOKIE_NAME ] = Signals::STATE_ACTIVE;
    $this->assertSame(
      Signals::STATE_ACTIVE,
      Signals::get_signal_state()
    );
    $this->assertFalse( Signals::should_hold_signals() );
  }

  /**
   * Test that the AJAX handler updates request cookie state.
   *
   * @return void
   */
  public function testHandleUpdateStateUpdatesCookieState() {
    $_POST['state'] = Signals::STATE_ACTIVE;

    \WP_Mock::userFunction(
      'check_ajax_referer',
      array(
        'args' => array( Signals::NONCE_ACTION, 'security' ),
      )
    );
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
    \WP_Mock::userFunction(
      'wp_unslash',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
    \WP_Mock::userFunction( 'is_ssl', array( 'return' => false ) );

    $captured_payload = null;
    \WP_Mock::userFunction(
      'wp_send_json_success',
      array(
        'return' => function ( $payload ) use ( &$captured_payload ) {
          $captured_payload = $payload;
          return $payload;
        },
      )
    );

    $handler = new Signals();
    $handler->handle_update_state();

    $this->assertEquals(
      Signals::STATE_ACTIVE,
      $_COOKIE[ Signals::COOKIE_NAME ]
    );
    $this->assertEquals(
      array( 'state' => Signals::STATE_ACTIVE ),
      $captured_payload
    );
  }

  /**
   * Helper: set up WP function mocks needed by handle_update_state().
   *
   * @param array|null $captured_payload Reference to capture wp_send_json_success payload.
   * @return void
   */
  private function mockHandleUpdateStateDeps( &$captured_payload ) {
    \WP_Mock::userFunction(
      'check_ajax_referer',
      array(
        'args' => array( Signals::NONCE_ACTION, 'security' ),
      )
    );
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
    \WP_Mock::userFunction(
      'wp_unslash',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
    \WP_Mock::userFunction( 'is_ssl', array( 'return' => false ) );
    \WP_Mock::userFunction(
      'wp_send_json_success',
      array(
        'return' => function ( $payload ) use ( &$captured_payload ) {
          $captured_payload = $payload;
          return $payload;
        },
      )
    );
  }

  /**
   * Test handle_update_state() defaults to held when state param is absent.
   *
   * @return void
   */
  public function testHandleUpdateStateDefaultsToHeldWhenParamMissing() {
    unset( $_POST['state'] );
    $captured_payload = null;
    $this->mockHandleUpdateStateDeps( $captured_payload );

    ( new Signals() )->handle_update_state();

    $this->assertSame(
      Signals::STATE_HELD,
      $_COOKIE[ Signals::COOKIE_NAME ]
    );
    $this->assertEquals(
      array( 'state' => Signals::STATE_HELD ),
      $captured_payload
    );
  }

  /**
   * Test handle_update_state() with explicit state='held'.
   *
   * @return void
   */
  public function testHandleUpdateStateExplicitHeld() {
    $_POST['state'] = Signals::STATE_HELD;
    $captured_payload = null;
    $this->mockHandleUpdateStateDeps( $captured_payload );

    ( new Signals() )->handle_update_state();

    $this->assertSame(
      Signals::STATE_HELD,
      $_COOKIE[ Signals::COOKIE_NAME ]
    );
    $this->assertEquals(
      array( 'state' => Signals::STATE_HELD ),
      $captured_payload
    );
  }

  /**
   * Test handle_update_state() normalises invalid values to held.
   *
   * @dataProvider provideInvalidStateValues
   *
   * @param string $invalid_value
   * @return void
   */
  public function testHandleUpdateStateInvalidValueBecomesHeld( $invalid_value ) {
    $_POST['state'] = $invalid_value;
    $captured_payload = null;
    $this->mockHandleUpdateStateDeps( $captured_payload );

    ( new Signals() )->handle_update_state();

    $this->assertSame(
      Signals::STATE_HELD,
      $_COOKIE[ Signals::COOKIE_NAME ]
    );
    $this->assertEquals(
      array( 'state' => Signals::STATE_HELD ),
      $captured_payload
    );
  }

  /**
   * @return array<array<string>>
   */
  public static function provideInvalidStateValues() {
    return array(
      'garbage string' => array( 'garbage' ),
      'empty string'   => array( '' ),
      'numeric one'    => array( '1' ),
      'granted legacy' => array( 'granted' ),
    );
  }

  /**
   * Test that an unrecognised cookie value is treated as unset by all predicates.
   *
   * @return void
   */
  public function testUnrecognisedCookieValueTreatedAsUnset() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
    \WP_Mock::userFunction(
      'wp_unslash',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );

    $_COOKIE[ Signals::COOKIE_NAME ] = 'garbage';
    $this->assertNull( Signals::get_signal_state() );
    $this->assertFalse( Signals::is_signals_active() );
    $this->assertFalse( Signals::should_hold_signals() );
  }

  /**
   * Test is_signals_active() mirrors should_hold_signals() inverse.
   *
   * @return void
   */
  public function testIsSignalsActiveReturnsTrueOnlyWhenActive() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
    \WP_Mock::userFunction(
      'wp_unslash',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );

    // Unset cookie — neither active nor held.
    unset( $_COOKIE[ Signals::COOKIE_NAME ] );
    $this->assertFalse( Signals::is_signals_active() );

    // Explicitly held.
    $_COOKIE[ Signals::COOKIE_NAME ] = Signals::STATE_HELD;
    $this->assertFalse( Signals::is_signals_active() );

    // Explicitly active.
    $_COOKIE[ Signals::COOKIE_NAME ] = Signals::STATE_ACTIVE;
    $this->assertTrue( Signals::is_signals_active() );
  }

  /**
   * Stubs the render/build helpers track() and render() rely on.
   *
   * @return void
   */
  private function mockEventHelpers() {
    self::mockFacebookWordpressOptions();
    \WP_Mock::userFunction(
      'wp_json_encode',
      array(
        'return' => function ( $data, $options = 0 ) {
          return json_encode( $data );
        },
      )
    );
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
  }

  /**
   * track() builds the CAPI event, drops empty fields (keeping 0), tracks it,
   * and returns it.
   *
   * @return void
   */
  public function testTrackBuildsTracksAndReturnsEvent() {
    $this->mockEventHelpers();

    $signals = new Signals();
    $event   = $signals->track(
      'Lead',
      array(
        'email' => 'pika.chu@s2s.com',
        'value' => 0,
        'phone' => '',
        'note'  => null,
      ),
      'contact-form-7'
    );

    $this->assertEquals( 'Lead', $event->getEventName() );
    $this->assertEquals( 0, $event->getCustomData()->getValue() );
    $this->assertEquals(
      'contact-form-7',
      $event->getCustomData()->getCustomProperty( 'fb_integration_tracking' )
    );

    $tracked = FacebookServerSideEvent::get_instance()->get_tracked_events();
    $this->assertCount( 1, $tracked );
    $this->assertSame( $event, $tracked[0] );
  }

  /**
   * track() stamps a supplied shared id onto the event for dedup.
   *
   * @return void
   */
  public function testTrackSetsSharedEventId() {
    $this->mockEventHelpers();

    $signals = new Signals();
    $event   = $signals->track(
      'AddToCart',
      array( 'content_ids' => array( '1' ) ),
      'easy-digital-downloads',
      'shared-123'
    );

    $this->assertEquals( 'shared-123', $event->getEventId() );
  }

  /**
   * render() turns a tracked event into browser pixel code.
   *
   * @return void
   */
  public function testRenderReturnsPixelCode() {
    $this->mockEventHelpers();

    $signals = new Signals();
    $event   = $signals->track(
      'Lead',
      array( 'email' => 'pika.chu@s2s.com' ),
      'contact-form-7'
    );

    $code = $signals->render( $event, 'contact-form-7' );

    $this->assertStringContainsString( 'Lead', $code );
    $this->assertStringContainsString( 'contact-form-7', $code );
  }

  /**
   * flush_pending_events() sends the queued events via send_server_events.
   *
   * @return void
   */
  public function testFlushPendingEventsSendsQueuedEvents() {
    $event = new \stdClass();
    $store = $this->createMock( FacebookServerSideEvent::class );
    $store->method( 'get_pending_events' )->willReturn( array( $event ) );
    $signals = new Signals( $store );

    \WP_Mock::expectAction( 'send_server_events', array( $event ), 1 );

    $signals->flush_pending_events();

    $this->assertConditionsMet();
  }

  /**
   * flush_pending_events() does nothing when nothing is queued.
   *
   * @return void
   */
  public function testFlushPendingEventsNoopWhenEmpty() {
    $store = $this->createMock( FacebookServerSideEvent::class );
    $store->method( 'get_pending_events' )->willReturn( array() );
    $signals = new Signals( $store );

    $signals->flush_pending_events();

    $this->assertConditionsMet();
  }
}
