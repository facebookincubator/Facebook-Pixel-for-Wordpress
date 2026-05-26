<?php
/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\Signals;
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
  public function testHandleSetSignalsUpdatesCookieState() {
    $_POST['state'] = 'active';

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
    $this->assertEquals( array( 'state' => 'active' ), $captured_payload );
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
}
