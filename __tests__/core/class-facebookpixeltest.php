<?php
/**
 * Facebook Pixel Plugin FacebookPixelTest class.
 *
 * This file contains the main logic for FacebookPixelTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookPixelTest class.
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

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\FacebookPixel;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;


/**
 * FacebookPixelTest class.
 */
final class FacebookPixelTest extends FacebookWordpressTestBase {
	/**
	 * Tests the ability to initialize, get, and set the pixel ID.
	 *
	 * This test initializes the FacebookPixel with a pixel ID, verifies
	 * that the pixel ID can be retrieved correctly, changes the pixel ID,
	 * and verifies that the new pixel ID can be retrieved.
	 *
	 * @return void
	 */
	public function testCanGetAndSetPixelId() {
		FacebookPixel::initialize( '123' );
		$this->assertEquals( '123', FacebookPixel::get_pixel_id() );
		FacebookPixel::set_pixel_id( '1' );
		$this->assertEquals( '1', FacebookPixel::get_pixel_id() );
	}

	/**
	 * Asserts that the given code starts with <script and ends with </script.
	 *
	 * @param string $code The code to test.
	 *
	 * @return void
	 */
	private function assertCodeStartAndEndWithScript( $code ) {
		$this->assertStringStartsWith( '<script', $code );
		$this->assertStringEndsWith( '</script>', $code );
	}

	/**
	 * Asserts that the given code does not start with <script and does not end with </script.
	 *
	 * @param string $code The code to test.
	 *
	 * @return void
	 */
	private function assertCodeStartAndEndWithNoScript( $code ) {
		$this->assertStringStartsNotWith( '<script', $code );
		$this->assertStringEndsNotWith( '</script>', $code );
	}

	/**
	 * Asserts that the given code contains all the given keywords.
	 *
	 * @param string $code   The code to test.
	 * @param array  $keywords The keywords to search for.
	 *
	 * @return void
	 */
	private function assertCodePatternMatch( $code, $keywords ) {
		foreach ( $keywords as $keyword ) {
			$this->assertTrue( \strpos( $code, $keyword ) !== false );
		}
	}

	/**
	 * Asserts that the given function behaves correctly in terms of code pattern.
	 *
	 * It tests that when no pixel ID is set, the function returns an empty string.
	 * It tests that when a pixel ID is set, the function returns a string that starts
	 * with <script and ends with </script. It also tests that the string contains
	 * the given keyword, as well as the 'track' keyword.
	 *
	 * @param string $method_name The name of the method to test.
	 * @param string $keyword The keyword to search for.
	 *
	 * @return void
	 */
	private function assertCodePattern( $method_name, $keyword ) {
		FacebookPixel::set_pixel_id( '' );
		$code = FacebookPixel::$method_name();
		$this->assertEmpty( $code );

		FacebookPixel::set_pixel_id( '123' );
		$code = FacebookPixel::$method_name();
		$this->assertCodeStartAndEndWithScript( $code );
		$this->assertCodePatternMatch( $code, array( 'track', $keyword ) );
	}

	/**
	 * Tests that the get_pixel_init_code function behaves correctly.
	 *
	 * It tests that when no pixel ID is set, the function returns an empty string.
	 * It tests that when a pixel ID is set, the function returns a string that starts
	 * with <script and ends with </script. It also tests that the string contains
	 * the given keyword, as well as the 'track', 'init', and 'agent' keywords.
	 * It also tests that if the $with_script_tag parameter is set to false, the returned
	 * string does not start with <script and does not end with </script.
	 *
	 * @return void
	 */
	public function testCanGetPixelInitCode() {
		FacebookPixel::set_pixel_id( '' );
		$code = FacebookPixel::get_pixel_init_code( 'mockAgent', array( 'key' => 'value' ) );
		$this->assertEmpty( $code );

		FacebookPixel::set_pixel_id( '123' );
		$code = FacebookPixel::get_pixel_init_code( 'mockAgent', array( 'key' => 'value' ) );
		$this->assertCodeStartAndEndWithScript( $code );
		$this->assertCodePatternMatch( $code, array( '123', 'init', '"key": "value"', '"agent": "mockAgent"' ) );

		$code = FacebookPixel::get_pixel_init_code( 'mockAgent', '{"key": "value"}', false );
		$this->assertCodeStartAndEndWithNoScript( $code );
		$this->assertCodePatternMatch( $code, array( '123', 'init', '"key": "value"', '"agent": "mockAgent"' ) );
	}

	/**
	 * Tests that the get_pixel_track_code function behaves correctly.
	 *
	 * It tests that when no pixel ID is set, the function returns an empty string.
	 * It tests that when a pixel ID is set, the function returns a string that starts
	 * with <script and ends with </script. It also tests that the string contains
	 * the given keyword, as well as the 'track' keyword. It also tests that if the
	 * $with_script_tag parameter is set to false, the returned string does not start
	 * with <script and does not end with </script. If the $event_name parameter is
	 * not set, the 'trackCustom' keyword is used instead.
	 *
	 * @return void
	 */
	public function testCanGetPixelTrackCode() {
		FacebookPixel::set_pixel_id( '' );
		$code = FacebookPixel::get_pixel_track_code( 'mockEvent', array( 'key' => 'value' ) );
		$this->assertEmpty( $code );

		FacebookPixel::set_pixel_id( '123' );
		$code = FacebookPixel::get_pixel_track_code( 'mockEvent', array( 'key' => 'value' ) );
		$this->assertCodeStartAndEndWithScript( $code );
		$this->assertCodePatternMatch( $code, array( 'track', '"key": "value"', 'mockEvent' ) );

		$code = FacebookPixel::get_pixel_track_code( 'mockEvent', '{"key": "value"}', '', false );
		$this->assertCodeStartAndEndWithNoScript( $code );
		$this->assertCodePatternMatch( $code, array( 'trackCustom', '"key": "value"', 'mockEvent' ) );
	}

	/**
	 * Tests that the get_pixel_noscript_code function behaves correctly.
	 *
	 * It tests that when no pixel ID is set, the function returns an empty string.
	 * It tests that when a pixel ID is set, the function returns a string that
	 * contains the given pixel ID, the given event name, as well as the given
	 * parameters. The parameters should be URL encoded.
	 *
	 * @return void
	 */
	public function testCanGetPixelNoScriptCode() {
		FacebookPixel::set_pixel_id( '' );
		$code = FacebookPixel::get_pixel_noscript_code( 'mockEvent', array( 'key' => 'value' ) );
		$this->assertEmpty( $code );

		FacebookPixel::set_pixel_id( '123' );
		$code = FacebookPixel::get_pixel_noscript_code( 'mockEvent', array( 'key' => 'value' ) );
		$this->assertCodePatternMatch( $code, array( '123', 'mockEvent', '[key]=value' ) );
	}

	/**
	 * Tests that the get_pixel_add_to_cart_code function behaves correctly.
	 *
	 * It tests that when no pixel ID is set, the function returns an empty string.
	 * It tests that when a pixel ID is set, the function returns a string that
	 * contains the given pixel ID, the given event name, as well as the given
	 * parameters. The parameters should be URL encoded.
	 */
	public function testGetPixelAddToCartCode() {
		$this->assertCodePattern( 'get_pixel_add_to_cart_code', 'AddToCart' );
	}

	/**
	 * Tests that the get_pixel_initiate_checkout_code function behaves correctly.
	 *
	 * It tests that when no pixel ID is set, the function returns an empty string.
	 * It tests that when a pixel ID is set, the function returns a string that
	 * contains the given pixel ID, the given event name, as well as the given
	 * parameters. The parameters should be URL encoded.
	 *
	 * @return void
	 */
	public function testgGetPixelInitiateCheckoutCode() {
		$this->assertCodePattern( 'get_pixel_initiate_checkout_code', 'InitiateCheckout' );
	}

	/**
	 * Tests that the get_pixel_lead_code function behaves correctly.
	 *
	 * It tests that when no pixel ID is set, the function returns an empty string.
	 * It tests that when a pixel ID is set, the function returns a string that
	 * contains the given pixel ID, the 'Lead' event name, as well as the given
	 * parameters. The parameters should be URL encoded.
	 *
	 * @return void
	 */
	public function testGetPixelLeadCode() {
		$this->assertCodePattern( 'get_pixel_lead_code', 'Lead' );
	}

	/**
	 * Tests that the get_pixel_page_view_code function behaves correctly.
	 *
	 * It tests that when no pixel ID is set, the function returns an empty string.
	 * It tests that when a pixel ID is set, the function returns a string that
	 * contains the given pixel ID, the 'PageView' event name, as well as the given
	 * parameters. The parameters should be URL encoded.
	 *
	 * @return void
	 */
	public function testGetPixelPageViewCode() {
		$this->assertCodePattern( 'get_pixel_page_view_code', 'PageView' );
	}

	/**
	 * Tests that the get_pixel_purchase_code function behaves correctly.
	 *
	 * It tests that when no pixel ID is set, the function returns an empty string.
	 * It tests that when a pixel ID is set, the function returns a string that
	 * contains the given pixel ID, the 'Purchase' event name, as well as the given
	 * parameters. The parameters should be URL encoded.
	 *
	 * @return void
	 */
	public function testGetPixelPurchaseCode() {
		$this->assertCodePattern( 'get_pixel_purchase_code', 'Purchase' );
	}

	/**
	 * Tests that the get_pixel_view_content_code function behaves correctly.
	 *
	 * It tests that when no pixel ID is set, the function returns an empty string.
	 * It tests that when a pixel ID is set, the function returns a string that
	 * contains the given pixel ID, the 'ViewContent' event name, as well as the given
	 * parameters. The parameters should be URL encoded.
	 *
	 * @return void
	 */
	public function testGetPixelViewContentCode() {
		$this->assertCodePattern( 'get_pixel_view_content_code', 'ViewContent' );
	}
}
