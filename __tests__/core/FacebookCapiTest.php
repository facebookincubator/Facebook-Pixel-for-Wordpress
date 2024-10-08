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
		$custom_data = array(
						'value'       => '100',
						'invalid_key' => 'Invalid Data',
					);

		$invalid_data = $this->capi_event->get_invalid_event_custom_data( $custom_data );
		$this->assertContains( 'invalid_key', $invalid_data );
		$this->assertCount( 1, $invalid_data );
	}

	/**
	 * Test get_invalid_event_custom_data with valid data returns empty.
	 */
	public function test_valid_custom_data() {
		$custom_data = array(
						'value'    => '100',
						'currency' => 'USD',
					);

		$invalid_data = $this->capi_event->get_invalid_event_custom_data( $custom_data );
		$this->assertEmpty( $invalid_data );
	}

	/**
	 * Test send_capi_event fails with empty payload.
	 */
	public function test_empty_payload() {
		$_POST = array(
			'nonce'           => 'send_capi_event_nonce',
			'payload'         => null,
		);

		WP_Mock::userFunction('wp_verify_nonce', array(
			'args' => array(\WP_Mock\Functions::type( 'string' ),
			  \WP_Mock\Functions::type( 'string' )),
			'return' => true
		  )
		);

		WP_Mock::userFunction(
			'wp_send_json_error',
			array(
				'times' => 1,
				'args'  => array(
					json_encode(
						array(
							'error' => array(
								'message'        => 'Empty payload',
								'error_user_msg' => 'Payload is empty.',
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
	 * Test send_capi_event fails with invalid JSON in payload.
	 */
	public function test_invalid_json_in_payload() {
		$_POST = array(
			'nonce'           => 'send_capi_event_nonce',
			'event_name'      => 'Purchase',
			'test_event_code' => 'TEST1234',
			'payload'         => 'invalid_json',
		);

		WP_Mock::userFunction('wp_verify_nonce', array(
			'args' => array(\WP_Mock\Functions::type( 'string' ),
			  \WP_Mock\Functions::type( 'string' )),
			'return' => true
		  )
		);

		WP_Mock::userFunction(
			'wp_send_json_error',
			array(
				'times' => 1,
				'args'  => array(
					json_encode(
						array(
							'error' => array(
								'message'        => 'Invalid JSON in payload',
								'error_user_msg' => 'Invalid JSON in payload.',
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
	 * Test send_capi_event with missing required event data.
	 */
	public function test_missing_required_event_data() {
		$_POST = array(
			'nonce'           => 'send_capi_event_nonce',
			'event_name'      => 'Purchase',
			'test_event_code' => 'TEST1234',
			'payload'         => array(
				'data'            => array(
					array(
						'event_name'       => 'Purchase',
						'event_time'       => time(),
						'action_source'    => 'website',
						'event_source_url' => 'http://jaspers-market.com/product/123',
					)
				)
			),
		);

		WP_Mock::userFunction('wp_verify_nonce', array(
			'args' => array(\WP_Mock\Functions::type( 'string' ),
			  \WP_Mock\Functions::type( 'string' )),
			'return' => true
		  )
		);

		WP_Mock::userFunction(
			'wp_send_json_error',
			array(
				'times' => 1,
				'args'  => array(
					json_encode(
						array(
							'error' => array(
								'message'        => 'Missing required attribute',
								'error_user_msg' => 'user_data attribute is missing',
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
	 * Test send_capi_event fails with invalid user_data attribute type.
	 */
	public function test_event_data_invalid_type() {
		$_POST = array(
			'nonce'           => 'send_capi_event_nonce',
			'event_name'      => 'Purchase',
			'test_event_code' => 'TEST1234',
			'payload'         => array(
				'data'            => array(
					array(
						'event_name'       => 'Purchase',
						'event_time'       => time(),
						'action_source'    => 'website',
						'event_source_url' => 'http://jaspers-market.com/product/123',
						'user_data'        => 'invalid_type',
					)
				)
			),
		);

		WP_Mock::userFunction('wp_verify_nonce', array(
			'args' => array(\WP_Mock\Functions::type( 'string' ),
			  \WP_Mock\Functions::type( 'string' )),
			'return' => true
		  )
		);

		WP_Mock::userFunction(
			'wp_send_json_error',
			array(
				'times' => 1,
				'args'  => array(
					json_encode(
						array(
							'error' => array(
								'message'        => 'Invalid attribute type',
								'error_user_msg' => 'Invalid attribute type: user_data',
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
	 * Test send_capi_event fails with invalid custom_data attribute in payload.
	 */
	public function test_invalid_custom_data_in_payload() {
		$_POST = array(
			'nonce'           => 'send_capi_event_nonce',
			'event_name'      => 'Purchase',
			'test_event_code' => 'TEST1234',
			'payload'         => array(
				'data'            => array(
					array(
						'event_name'       => 'Purchase',
						'event_time'       => time(),
						'action_source'    => 'website',
						'event_source_url' => 'http://jaspers-market.com/product/123',
						'user_data'        => array(
							'em' => array(
								"309a0a5c3e211326ae75ca18196d301a9bdbd1a882a4d2569511033da23f0abd"
							),
							'ph' => array(
								"254aa248acb47dd654ca3ea53f48c2c26d641d23d7e2e93a1ec56258df7674c4"
							),
						),
						'custom_data' => array(
							'value'                   => '100',
							'currency'                => 'USD',
							'invalid_custom_data_key' => 'invalid_custom_data_value',
						),
					)
				)
			),
		);

		WP_Mock::userFunction('wp_verify_nonce', array(
			'args' => array(\WP_Mock\Functions::type( 'string' ),
			  \WP_Mock\Functions::type( 'string' )),
			'return' => true
		  )
		);

		WP_Mock::userFunction(
			'wp_send_json_error',
			array(
				'times' => 1,
				'args'  => array(
					json_encode(
						array(
							'error' => array(
								'message'        => 'Invalid custom_data attribute',
								'error_user_msg' => 'Invalid custom_data attributes: invalid_custom_data_key',
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
}
