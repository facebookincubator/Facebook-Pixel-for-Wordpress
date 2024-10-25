<?php
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
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in seperate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookPixelTest extends FacebookWordpressTestBase {
	public function testCanGetAndSetPixelId() {
		FacebookPixel::initialize( '123' );
		$this->assertEquals( '123', FacebookPixel::get_pixel_id() );
		FacebookPixel::set_pixel_id( '1' );
		$this->assertEquals( '1', FacebookPixel::get_pixel_id() );
	}

	private function assertCodeStartAndEndWithScript( $code ) {
		$this->assertStringStartsWith( '<script', $code );
		$this->assertStringEndsWith( '</script>', $code );
	}

	private function assertCodeStartAndEndWithNoScript( $code ) {
		$this->assertStringStartsNotWith( '<script', $code );
		$this->assertStringEndsNotWith( '</script>', $code );
	}
	private function assertCodePatternMatch( $code, $keywords ) {
		foreach ( $keywords as $keyword ) {
			$this->assertTrue( \strpos( $code, $keyword ) !== false );
		}
	}

	private function assertCodePattern( $function, $keyword ) {
		FacebookPixel::set_pixel_id( '' );
		$code = FacebookPixel::$function();
		$this->assertEmpty( $code );

		FacebookPixel::set_pixel_id( '123' );
		$code = FacebookPixel::$function();
		$this->assertCodeStartAndEndWithScript( $code );
		$this->assertCodePatternMatch( $code, array( 'track', $keyword ) );
	}

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

	public function testCanGetPixelNoScriptCode() {
		FacebookPixel::set_pixel_id( '' );
		$code = FacebookPixel::get_pixel_noscript_code( 'mockEvent', array( 'key' => 'value' ) );
		$this->assertEmpty( $code );

		FacebookPixel::set_pixel_id( '123' );
		$code = FacebookPixel::get_pixel_noscript_code( 'mockEvent', array( 'key' => 'value' ) );
		$this->assertCodePatternMatch( $code, array( '123', 'mockEvent', '[key]=value' ) );
	}

	public function testGetPixelAddToCartCode() {
		$this->assertCodePattern( 'get_pixel_add_to_cart_code', 'AddToCart' );
	}

	public function testgGetPixelInitiateCheckoutCode() {
		$this->assertCodePattern( 'get_pixel_initiate_checkout_code', 'InitiateCheckout' );
	}

	public function testGetPixelLeadCode() {
		$this->assertCodePattern( 'get_pixel_lead_code', 'Lead' );
	}

	public function testGetPixelPageViewCode() {
		$this->assertCodePattern( 'get_pixel_page_view_code', 'PageView' );
	}

	public function testGetPixelPurchaseCode() {
		$this->assertCodePattern( 'get_pixel_purchase_code', 'Purchase' );
	}

	public function testGetPixelViewContentCode() {
		$this->assertCodePattern( 'get_pixel_view_content_code', 'ViewContent' );
	}
}
