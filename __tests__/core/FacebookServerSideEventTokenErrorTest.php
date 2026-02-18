<?php
/**
 * Facebook Pixel Plugin FacebookServerSideEventTokenErrorTest class.
 *
 * This file contains tests for token error handling in FacebookServerSideEvent.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookServerSideEventTokenErrorTest class.
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

use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookServerSideEventTokenErrorTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookServerSideEventTokenErrorTest extends FacebookWordpressTestBase {

    /**
     * Sets up the test environment.
     *
     * Defines WordPress constants not available in WP_Mock.
     */
    public function setUp(): void {
        parent::setUp();
        if ( ! defined( 'DAY_IN_SECONDS' ) ) {
            define( 'DAY_IN_SECONDS', 86400 );
        }
    }

    /**
     * Tests that send() sets a transient when an AuthorizationException
     * is thrown by the SDK.
     */
    public function testSendSetsTransientOnAuthorizationException() {
        $this->mockFacebookWordpressOptions();

        $mock_api = \Mockery::mock(
            'alias:FacebookPixelPlugin\FacebookAds\Api'
        );
        $mock_api->shouldReceive( 'init' )
            ->andReturn( $mock_api );

        $auth_exception = \Mockery::mock(
            'FacebookPixelPlugin\FacebookAds\Http\Exception\AuthorizationException'
        );
        $auth_exception->shouldReceive( 'getCode' )->andReturn( 190 );
        $auth_exception->shouldReceive( 'getErrorSubcode' )->andReturn( 464 );
        $auth_exception->shouldReceive( 'getMessage' )
            ->andReturn( 'Error validating access token' );
        $auth_exception->shouldReceive( 'getTraceAsString' )->andReturn( '' );

        $mock_request = \Mockery::mock(
            'overload:FacebookPixelPlugin\FacebookAds\Object\ServerSide\EventRequest'
        );
        $mock_request->shouldReceive( 'setEvents' )
            ->andReturn( $mock_request );
        $mock_request->shouldReceive( 'setPartnerAgent' )
            ->andReturn( $mock_request );
        $mock_request->shouldReceive( 'execute' )
            ->andThrow( $auth_exception );

        $transient_set = false;
        \WP_Mock::userFunction(
            'set_transient',
            array(
                'times'  => 1,
                'args'   => array(
                    FacebookPluginConfig::TOKEN_INVALID_TRANSIENT_KEY,
                    \Mockery::type( 'array' ),
                    DAY_IN_SECONDS,
                ),
                'return' => function () use ( &$transient_set ) {
                    $transient_set = true;
                    return true;
                },
            )
        );

        $mock_event = \Mockery::mock(
            'FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event'
        );
        $mock_event->shouldReceive( 'getCustomData' )->andReturn( null );

        \WP_Mock::expectFilter(
            'before_conversions_api_event_sent',
            array( $mock_event )
        );

        FacebookServerSideEvent::send( array( $mock_event ) );

        $this->assertTrue( $transient_set );
    }

    /**
     * Tests that send() clears the transient on a successful API call
     * when the transient exists.
     */
    public function testSendClearsTransientOnSuccess() {
        $this->mockFacebookWordpressOptions();

        $mock_api = \Mockery::mock(
            'alias:FacebookPixelPlugin\FacebookAds\Api'
        );
        $mock_api->shouldReceive( 'init' )
            ->andReturn( $mock_api );

        $mock_response = \Mockery::mock();

        $mock_request = \Mockery::mock(
            'overload:FacebookPixelPlugin\FacebookAds\Object\ServerSide\EventRequest'
        );
        $mock_request->shouldReceive( 'setEvents' )
            ->andReturn( $mock_request );
        $mock_request->shouldReceive( 'setPartnerAgent' )
            ->andReturn( $mock_request );
        $mock_request->shouldReceive( 'execute' )
            ->andReturn( $mock_response );

        \WP_Mock::userFunction(
            'get_transient',
            array(
                'times'  => 1,
                'args'   => array(
                    FacebookPluginConfig::TOKEN_INVALID_TRANSIENT_KEY,
                ),
                'return' => array(
                    'code'    => 190,
                    'subcode' => 464,
                ),
            )
        );

        \WP_Mock::userFunction(
            'delete_transient',
            array(
                'times' => 1,
                'args'  => array(
                    FacebookPluginConfig::TOKEN_INVALID_TRANSIENT_KEY,
                ),
            )
        );

        $mock_event = \Mockery::mock(
            'FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event'
        );
        $mock_event->shouldReceive( 'getCustomData' )->andReturn( null );

        \WP_Mock::expectFilter(
            'before_conversions_api_event_sent',
            array( $mock_event )
        );

        FacebookServerSideEvent::send( array( $mock_event ) );
    }

    /**
     * Tests that send() does NOT set a transient when a generic
     * (non-auth) exception is thrown.
     */
    public function testSendDoesNotSetTransientOnGenericException() {
        $this->mockFacebookWordpressOptions();

        $mock_api = \Mockery::mock(
            'alias:FacebookPixelPlugin\FacebookAds\Api'
        );
        $mock_api->shouldReceive( 'init' )
            ->andReturn( $mock_api );

        $mock_request = \Mockery::mock(
            'overload:FacebookPixelPlugin\FacebookAds\Object\ServerSide\EventRequest'
        );
        $mock_request->shouldReceive( 'setEvents' )
            ->andReturn( $mock_request );
        $mock_request->shouldReceive( 'setPartnerAgent' )
            ->andReturn( $mock_request );
        $mock_request->shouldReceive( 'execute' )
            ->andThrow( new \Exception( 'Network error' ) );

        \WP_Mock::userFunction(
            'set_transient',
            array(
                'times' => 0,
            )
        );

        $mock_event = \Mockery::mock(
            'FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event'
        );
        $mock_event->shouldReceive( 'getCustomData' )->andReturn( null );

        \WP_Mock::expectFilter(
            'before_conversions_api_event_sent',
            array( $mock_event )
        );

        FacebookServerSideEvent::send( array( $mock_event ) );
    }

    /**
     * Tests that send() does NOT call delete_transient when no
     * transient exists after a successful API call.
     */
    public function testSendDoesNotClearTransientWhenNoneExists() {
        $this->mockFacebookWordpressOptions();

        $mock_api = \Mockery::mock(
            'alias:FacebookPixelPlugin\FacebookAds\Api'
        );
        $mock_api->shouldReceive( 'init' )
            ->andReturn( $mock_api );

        $mock_response = \Mockery::mock();

        $mock_request = \Mockery::mock(
            'overload:FacebookPixelPlugin\FacebookAds\Object\ServerSide\EventRequest'
        );
        $mock_request->shouldReceive( 'setEvents' )
            ->andReturn( $mock_request );
        $mock_request->shouldReceive( 'setPartnerAgent' )
            ->andReturn( $mock_request );
        $mock_request->shouldReceive( 'execute' )
            ->andReturn( $mock_response );

        \WP_Mock::userFunction(
            'get_transient',
            array(
                'times'  => 1,
                'args'   => array(
                    FacebookPluginConfig::TOKEN_INVALID_TRANSIENT_KEY,
                ),
                'return' => false,
            )
        );

        \WP_Mock::userFunction(
            'delete_transient',
            array(
                'times' => 0,
            )
        );

        $mock_event = \Mockery::mock(
            'FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event'
        );
        $mock_event->shouldReceive( 'getCustomData' )->andReturn( null );

        \WP_Mock::expectFilter(
            'before_conversions_api_event_sent',
            array( $mock_event )
        );

        FacebookServerSideEvent::send( array( $mock_event ) );
    }
}
