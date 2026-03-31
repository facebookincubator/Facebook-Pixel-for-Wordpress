<?php
/**
 * Facebook Pixel Plugin FacebookParamBuilderTest class.
 *
 * @package FacebookPixelPlugin
 */

/**
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

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\FacebookParamBuilder;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookParamBuilderTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookParamBuilderTest extends FacebookWordpressTestBase {

	/**
	 * Reset ParamBuilder static state before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		// Reset the static singleton via reflection.
		$reflection = new \ReflectionClass( FacebookParamBuilder::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		$setup_done = $reflection->getProperty( 'server_setup_done' );
		$setup_done->setAccessible( true );
		$setup_done->setValue( null, false );
	}

	/**
	 * Tests that get_instance returns a ParamBuilder or null.
	 */
	public function testGetInstanceReturnsValueOrNull() {
		\WP_Mock::userFunction(
			'get_site_url',
			array( 'return' => 'https://www.example.com' )
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
			'wp_unslash',
			array(
				'args'   => array( \Mockery::any() ),
				'return' => function ( $input ) {
					return $input;
				},
			)
		);

		$instance = FacebookParamBuilder::get_instance();
		// Should return an instance or null (if the class is not available).
		$this->assertTrue(
			null === $instance || $instance instanceof \FacebookAds\ParamBuilder
		);
	}

	/**
	 * Tests that get_fbc returns null when no data is available.
	 */
	public function testGetFbcReturnsNullWhenNoData() {
		\WP_Mock::userFunction(
			'get_site_url',
			array( 'return' => 'https://www.example.com' )
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
			'wp_unslash',
			array(
				'args'   => array( \Mockery::any() ),
				'return' => function ( $input ) {
					return $input;
				},
			)
		);

		// With no cookies or query params, fbc should be null.
		$fbc = FacebookParamBuilder::get_fbc();
		$this->assertNull( $fbc );
	}

	/**
	 * Tests that get_fbp returns a string or null.
	 *
	 * ParamBuilder may generate an FBP value even without cookies,
	 * so we just verify the type is correct.
	 */
	public function testGetFbpReturnsStringOrNull() {
		\WP_Mock::userFunction(
			'get_site_url',
			array( 'return' => 'https://www.example.com' )
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
			'wp_unslash',
			array(
				'args'   => array( \Mockery::any() ),
				'return' => function ( $input ) {
					return $input;
				},
			)
		);

		$fbp = FacebookParamBuilder::get_fbp();
		$this->assertTrue( null === $fbp || is_string( $fbp ) );
	}

	/**
	 * Tests that server_setup does not error when called.
	 */
	public function testServerSetupDoesNotError() {
		\WP_Mock::userFunction(
			'get_site_url',
			array( 'return' => 'https://www.example.com' )
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
			'wp_unslash',
			array(
				'args'   => array( \Mockery::any() ),
				'return' => function ( $input ) {
					return $input;
				},
			)
		);

		// Should not throw any exception.
		FacebookParamBuilder::server_setup();
		$this->assertTrue( true );
	}

	/**
	 * Tests that server_setup is idempotent.
	 */
	public function testServerSetupIsIdempotent() {
		\WP_Mock::userFunction(
			'get_site_url',
			array( 'return' => 'https://www.example.com' )
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
			'wp_unslash',
			array(
				'args'   => array( \Mockery::any() ),
				'return' => function ( $input ) {
					return $input;
				},
			)
		);

		// Calling twice should not error.
		FacebookParamBuilder::server_setup();
		FacebookParamBuilder::server_setup();
		$this->assertTrue( true );
	}

	/**
	 * Tests that client_setup does nothing when pixel ID is not set.
	 */
	public function testClientSetupDoesNothingWithoutPixelId() {
		$mocked_options = \Mockery::mock(
			'alias:FacebookPixelPlugin\Core\FacebookWordpressOptions'
		);
		$mocked_options->shouldReceive( 'get_pixel_id' )
			->andReturn( '' );

		$mocked_utils = \Mockery::mock(
			'alias:FacebookPixelPlugin\Core\FacebookPluginUtils'
		);
		$mocked_utils->shouldReceive( 'is_positive_integer' )
			->with( '' )
			->andReturn( false );

		// wp_enqueue_script should NOT be called.
		\WP_Mock::userFunction(
			'wp_enqueue_script',
			array( 'times' => 0 )
		);

		FacebookParamBuilder::client_setup();
	}

	/**
	 * Tests that client_setup enqueues the script when pixel ID is set.
	 */
	public function testClientSetupEnqueuesScriptWithPixelId() {
		$mocked_options = \Mockery::mock(
			'alias:FacebookPixelPlugin\Core\FacebookWordpressOptions'
		);
		$mocked_options->shouldReceive( 'get_pixel_id' )
			->andReturn( '1234' );

		$mocked_utils = \Mockery::mock(
			'alias:FacebookPixelPlugin\Core\FacebookPluginUtils'
		);
		$mocked_utils->shouldReceive( 'is_positive_integer' )
			->with( '1234' )
			->andReturn( true );

		\WP_Mock::userFunction(
			'wp_enqueue_script',
			array(
				'times' => 1,
				'args'  => array(
					FacebookParamBuilder::CLIENT_JS_HANDLE,
					FacebookParamBuilder::CLIENT_JS_URL,
					array(),
					null,
					true,
				),
			)
		);

		\WP_Mock::userFunction(
			'wp_add_inline_script',
			array(
				'times' => 1,
				'args'  => array(
					FacebookParamBuilder::CLIENT_JS_HANDLE,
					\Mockery::type( 'string' ),
				),
			)
		);

		FacebookParamBuilder::client_setup();
	}

	/**
	 * Tests that get_instance returns the same singleton instance.
	 */
	public function testGetInstanceReturnsSingleton() {
		\WP_Mock::userFunction(
			'get_site_url',
			array( 'return' => 'https://www.example.com' )
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
			'wp_unslash',
			array(
				'args'   => array( \Mockery::any() ),
				'return' => function ( $input ) {
					return $input;
				},
			)
		);

		$instance1 = FacebookParamBuilder::get_instance();
		$instance2 = FacebookParamBuilder::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}
}
