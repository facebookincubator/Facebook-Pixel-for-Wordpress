<?php

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\FacebookCapiEvent;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookCapiTest extends FacebookWordpressTestBase {

	/**
	 * Setup the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->capi_event = new FacebookCapiEvent();

		WP_Mock::userFunction(
			'wp_die',
			array(
				'return' => function () {
					throw new \Exception( 'Execution halted' );
				},
			)
		);
	}

	/**
	 * Test get_event_custom_data returns an empty array.
	 */
	public function test_get_event_custom_data_returns_empty_array() {
		$custom_data = array();
		$result      = FacebookCapiEvent::get_event_custom_data( $custom_data );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_event_custom_data returns a non-empty array.
	 */
	public function test_get_event_custom_data_returns_non_empty_array() {
		$custom_data = array(
			'value'    => '100',
			'currency' => 'USD',
		);
		$result      = FacebookCapiEvent::get_event_custom_data( $custom_data );
		$this->assertIsArray( $result );
		$this->assertEquals( $custom_data, $result );
	}

	/**
	 * Test send_capi_event fails without nonce.
	 */
	public function test_send_capi_event_fails_without_nonce() {
		$_POST = array(
			'event_name'  => 'Purchase',
			'custom_data' => array(
				'value'    => '100',
				'currency' => 'USD',
			),
		);

		WP_Mock::userFunction(
			'wp_send_json_error',
			array(
				'times' => 1,
				'args'  => array(
					json_encode(
						array(
							'error' => array(
								'message'        => 'Invalid nonce',
								'error_user_msg' => 'Invalid nonce',
							),
						)
					),
				),
			)
		);

		try {
			$this->capi_event->send_capi_event();
			$this->fail( 'wp_die() was expected to halt execution, but it did not.' );
		} catch ( \Exception $e ) {
			$this->assertEquals( 'Execution halted', $e->getMessage() );
		}
	}

	/**
	 * Test get_invalid_event_custom_data returns invalid keys.
	 */
	public function test_get_invalid_event_custom_data() {
		$payload = array(
			'data' => array(
				array(
					'custom_data' => array(
						'value'       => '100',
						'invalid_key' => 'Invalid Data',
					),
				),
			),
		);

		$invalid_data = $this->capi_event->get_invalid_event_custom_data( $payload );
		$this->assertContains( 'invalid_key', $invalid_data );
		$this->assertCount( 1, $invalid_data );
	}

	/**
	 * Test get_invalid_event_custom_data with valid data returns empty.
	 */
	public function test_valid_custom_data() {
		$payload = array(
			'data' => array(
				array(
					'custom_data' => array(
						'value'    => '100',
						'currency' => 'USD',
					),
				),
			),
		);

		$invalid_data = $this->capi_event->get_invalid_event_custom_data( $payload );
		$this->assertEmpty( $invalid_data );
	}
}
