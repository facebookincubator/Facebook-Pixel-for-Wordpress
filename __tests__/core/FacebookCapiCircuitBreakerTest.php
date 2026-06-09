<?php
/**
 * Facebook Pixel Plugin FacebookCapiCircuitBreakerTest class.
 *
 * @package FacebookPixelPlugin
 */

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\FacebookCapiCircuitBreaker;
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookCapiCircuitBreakerTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookCapiCircuitBreakerTest extends FacebookWordpressTestBase {

  /**
   * Tests that sending is allowed when no transient is set.
   */
  public function testSendAllowedWhenNoTransient() {
    \WP_Mock::userFunction(
      'get_transient',
      array(
        'args'   => array(
          FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT,
        ),
        'return' => false,
      )
    );

    $this->assertTrue( FacebookCapiCircuitBreaker::is_send_allowed() );
  }

  /**
   * Tests that sending is blocked when transient is fresh (circuit open).
   */
  public function testSendBlockedWhenTransientFresh() {
    \WP_Mock::userFunction(
      'get_transient',
      array(
        'args'   => array(
          FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT,
        ),
        'return' => time() - 60,
      )
    );

    $this->assertFalse( FacebookCapiCircuitBreaker::is_send_allowed() );
  }

  /**
   * Tests that sending is allowed when transient is stale (half-open).
   */
  public function testSendAllowedWhenTransientStale() {
    \WP_Mock::userFunction(
      'get_transient',
      array(
        'args'   => array(
          FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT,
        ),
        'return' => time() - FacebookPluginConfig::CONNECTION_RETRY_INTERVAL - 1,
      )
    );

    $this->assertTrue( FacebookCapiCircuitBreaker::is_send_allowed() );
  }

  /**
   * Tests that is_tripped returns false when no transient is set.
   */
  public function testNotTrippedWhenNoTransient() {
    \WP_Mock::userFunction(
      'get_transient',
      array(
        'args'   => array(
          FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT,
        ),
        'return' => false,
      )
    );

    $this->assertFalse( FacebookCapiCircuitBreaker::is_tripped() );
  }

  /**
   * Tests that is_tripped returns true when transient is set.
   */
  public function testTrippedWhenTransientSet() {
    \WP_Mock::userFunction(
      'get_transient',
      array(
        'args'   => array(
          FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT,
        ),
        'return' => time(),
      )
    );

    $this->assertTrue( FacebookCapiCircuitBreaker::is_tripped() );
  }

  /**
   * Tests that record_success clears the transient when tripped.
   */
  public function testRecordSuccessClearsTransientWhenTripped() {
    \WP_Mock::userFunction(
      'get_transient',
      array(
        'args'   => array(
          FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT,
        ),
        'return' => time() - 100,
      )
    );

    \WP_Mock::userFunction(
      'delete_transient',
      array(
        'args'   => array(
          FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT,
        ),
        'times'  => 1,
        'return' => true,
      )
    );

    FacebookCapiCircuitBreaker::record_success();
  }

  /**
   * Tests that record_success does nothing when not tripped.
   */
  public function testRecordSuccessNoOpWhenNotTripped() {
    \WP_Mock::userFunction(
      'get_transient',
      array(
        'args'   => array(
          FacebookPluginConfig::CONNECTION_INVALID_TRANSIENT,
        ),
        'return' => false,
      )
    );

    \WP_Mock::userFunction(
      'delete_transient',
      array(
        'times'  => 0,
      )
    );

    FacebookCapiCircuitBreaker::record_success();
  }

  /**
   * Tests that record_exception trips the breaker for AuthorizationException.
   */
  public function testRecordExceptionTripsForAuthError() {
    $response = \Mockery::mock(
      'FacebookPixelPlugin\FacebookAds\Http\ResponseInterface'
    );
    $response->shouldReceive( 'getHeaders' )->andReturn( null );
    $response->shouldReceive( 'getContent' )->andReturn(
      array(
        'error' => array(
          'code'            => 190,
          'error_subcode'   => 464,
          'message'         => 'User not confirmed',
          'type'            => 'OAuthException',
          'error_user_title' => null,
          'error_user_msg'  => null,
        ),
      )
    );

    $exception = new \FacebookPixelPlugin\FacebookAds\Http\Exception\AuthorizationException( $response );

    \WP_Mock::userFunction(
      'set_transient',
      array(
        'times'  => 1,
        'return' => true,
      )
    );

    FacebookCapiCircuitBreaker::record_exception( $exception );
  }

  /**
   * Tests that record_exception does NOT trip for subcode 452.
   */
  public function testRecordExceptionSkipsSubcode452() {
    $response = \Mockery::mock(
      'FacebookPixelPlugin\FacebookAds\Http\ResponseInterface'
    );
    $response->shouldReceive( 'getHeaders' )->andReturn( null );
    $response->shouldReceive( 'getContent' )->andReturn(
      array(
        'error' => array(
          'code'            => 190,
          'error_subcode'   => 452,
          'message'         => 'Session mismatch',
          'type'            => 'OAuthException',
          'error_user_title' => null,
          'error_user_msg'  => null,
        ),
      )
    );

    $exception = new \FacebookPixelPlugin\FacebookAds\Http\Exception\AuthorizationException( $response );

    \WP_Mock::userFunction(
      'set_transient',
      array(
        'times'  => 0,
      )
    );

    FacebookCapiCircuitBreaker::record_exception( $exception );
  }

  /**
   * Tests that record_exception trips for PermissionException.
   */
  public function testRecordExceptionTripsForPermissionError() {
    $response = \Mockery::mock(
      'FacebookPixelPlugin\FacebookAds\Http\ResponseInterface'
    );
    $response->shouldReceive( 'getHeaders' )->andReturn( null );
    $response->shouldReceive( 'getContent' )->andReturn(
      array(
        'error' => array(
          'code'            => 200,
          'message'         => 'Permission denied',
          'type'            => 'OAuthException',
          'error_user_title' => null,
          'error_user_msg'  => null,
        ),
      )
    );

    $exception = new \FacebookPixelPlugin\FacebookAds\Http\Exception\PermissionException( $response );

    \WP_Mock::userFunction(
      'set_transient',
      array(
        'times'  => 1,
        'return' => true,
      )
    );

    FacebookCapiCircuitBreaker::record_exception( $exception );
  }

  /**
   * Tests that record_exception does NOT trip for generic exceptions.
   */
  public function testRecordExceptionIgnoresGenericException() {
    $exception = new \Exception( 'Network error' );

    \WP_Mock::userFunction(
      'set_transient',
      array(
        'times'  => 0,
      )
    );

    FacebookCapiCircuitBreaker::record_exception( $exception );
  }
}
