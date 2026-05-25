<?php
/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\FacebookPixelSignals;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookPixelSignalsTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookPixelSignalsTest extends FacebookWordpressTestBase {
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

    $this->assertNull( FacebookPixelSignals::get_signal_state() );
    $this->assertFalse( FacebookPixelSignals::should_hold_signals() );

    $_COOKIE[ FacebookPixelSignals::COOKIE_NAME ] = FacebookPixelSignals::STATE_HELD;
    $this->assertSame(
      FacebookPixelSignals::STATE_HELD,
      FacebookPixelSignals::get_signal_state()
    );
    $this->assertTrue( FacebookPixelSignals::should_hold_signals() );

    $_COOKIE[ FacebookPixelSignals::COOKIE_NAME ] = FacebookPixelSignals::STATE_ACTIVE;
    $this->assertSame(
      FacebookPixelSignals::STATE_ACTIVE,
      FacebookPixelSignals::get_signal_state()
    );
    $this->assertFalse( FacebookPixelSignals::should_hold_signals() );
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
        'args' => array( FacebookPixelSignals::NONCE_ACTION, 'security' ),
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

    $handler = new FacebookPixelSignals();
    $handler->handle_update_state();

    $this->assertEquals(
      FacebookPixelSignals::STATE_ACTIVE,
      $_COOKIE[ FacebookPixelSignals::COOKIE_NAME ]
    );
    $this->assertEquals( array( 'state' => 'active' ), $captured_payload );
  }
}
