<?php
/**
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\ReleaseSignalsAjax;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * ReleaseSignalsAjaxTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ReleaseSignalsAjaxTest extends FacebookWordpressTestBase {
  /**
   * Test queued event validation rules.
   *
   * @return void
   */
  public function testValidateEventRejectsUnknownAndStaleEvents() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
    \WP_Mock::userFunction(
      'absint',
      array(
        'return' => function ( $value ) {
          return (int) $value;
        },
      )
    );

    $handler = new ReleaseSignalsAjax();
    $method  = new \ReflectionMethod( ReleaseSignalsAjax::class, 'validate_event' );
    if ( PHP_VERSION_ID < 80100 ) {
      $method->setAccessible( true );
    }

    $this->assertFalse(
      $method->invoke(
        $handler,
        array(
          'event_name' => 'Bad Name!',
          'event_time' => time(),
        ),
        time()
      )
    );

    $this->assertFalse(
      $method->invoke(
        $handler,
        array(
          'event_name' => 'Purchase',
          'event_time' => time() - 3600,
        ),
        time()
      )
    );

    $this->assertTrue(
      $method->invoke(
        $handler,
        array(
          'event_name' => 'Purchase',
          'event_time' => time(),
        ),
        time()
      )
    );
  }

  /**
   * Test released event construction keeps source URL and event metadata.
   *
   * @return void
   */
  public function testBuildEventUsesQueuedPayload() {
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
    \WP_Mock::userFunction(
      'esc_url_raw',
      array(
        'return' => function ( $value ) {
          return $value;
        },
      )
    );
    \WP_Mock::userFunction(
      'absint',
      array(
        'return' => function ( $value ) {
          return (int) $value;
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

    $handler = new ReleaseSignalsAjax();
    $method  = new \ReflectionMethod( ReleaseSignalsAjax::class, 'build_event' );
    if ( PHP_VERSION_ID < 80100 ) {
      $method->setAccessible( true );
    }

    $event = $method->invoke(
      $handler,
      array(
        'event_name'       => 'Purchase',
        'event_id'         => 'evt-1',
        'event_time'       => 1234,
        'event_source_url' => 'https://www.pikachu.com/product/1',
        'custom_data'      => array(
          'currency' => 'USD',
          'value'    => 10,
        ),
      )
    );

    $this->assertEquals( 'Purchase', $event->getEventName() );
    $this->assertEquals( 'evt-1', $event->getEventId() );
    $this->assertEquals( 1234, $event->getEventTime() );
    $this->assertEquals(
      'https://www.pikachu.com/product/1',
      $event->getEventSourceUrl()
    );
    $this->assertEquals(
      'usd',
      $event->getCustomData()->normalize()['currency']
    );
  }
}
