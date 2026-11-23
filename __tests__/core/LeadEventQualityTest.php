<?php
/**
 * Facebook Pixel Plugin LeadEventQualityTest class.
 *
 * @package FacebookPixelPlugin
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

use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Core\LeadEventQuality;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * LeadEventQualityTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LeadEventQualityTest extends FacebookWordpressTestBase {

    /**
     * A Dataset Quality API response with a Lead and a PageView event.
     *
     * @return array
     */
    private function response_body() {
        return array(
            'web' => array(
                array(
                    'event_name'          => 'PageView',
                    'event_match_quality' => array( 'composite_score' => 4.1 ),
                ),
                array(
                    'event_name'          => 'Lead',
                    'event_match_quality' => array(
                        'composite_score'    => 7.25,
                        'match_key_feedback' => array(
                            array(
                                'identifier' => 'ip_address',
                                'coverage'   => array( 'percentage' => 100 ),
                            ),
                            array(
                                'identifier' => 'email',
                                'coverage'   => array( 'percentage' => 62.48 ),
                            ),
                            array(
                                'identifier' => 'some_new_key',
                                'coverage'   => array( 'percentage' => 80 ),
                            ),
                            array( 'coverage' => array( 'percentage' => 90 ) ),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * The cache fingerprint of a pixel and token, as LeadEventQuality keys it.
     *
     * @param string $pixel_id     The pixel id.
     * @param string $access_token The token.
     * @return string
     */
    private function fingerprint( $pixel_id, $access_token ) {
        return hash_hmac( 'sha256', $pixel_id . '|' . $access_token, 'e2e-salt' );
    }

    /**
     * Mocks wp_salt(), which keys the cache fingerprint.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        \WP_Mock::userFunction(
            'wp_salt',
            array(
                'args'   => array( 'auth' ),
                'return' => 'e2e-salt',
            )
        );
    }

    /**
     * Mocks the HTTP functions around one Dataset Quality API call.
     *
     * @param int|\WP_Error $code The response status, or an error.
     * @param array         $body The decoded response body.
     * @return void
     */
    private function mock_http( $code, $body = array() ) {
        \WP_Mock::userFunction(
            'add_query_arg',
            array(
                'return' => function ( $args, $url ) {
                    $query = array();
                    foreach ( $args as $key => $value ) {
                        $query[] = $key . '=' . $value;
                    }
                    return $url . '?' . implode( '&', $query );
                },
            )
        );
        \WP_Mock::userFunction(
            'wp_remote_get',
            array(
                'times'  => 1,
                'args'   => array(
                    'https://graph.facebook.com/' . FacebookPluginConfig::GRAPH_API_VERSION
                        . '/dataset_quality?dataset_id=123&agent_name=wordpress&fields='
                        . rawurlencode( LeadEventQuality::FIELDS ),
                    \Mockery::on(
                        function ( $options ) {
                            return 'Bearer token' === $options['headers']['Authorization'];
                        }
                    ),
                ),
                'return' => array( 'response' => $code ),
            )
        );
        \WP_Mock::userFunction( 'is_wp_error', array( 'return' => false ) );
        \WP_Mock::userFunction( 'wp_remote_retrieve_response_code', array( 'return' => $code ) );
        \WP_Mock::userFunction( 'wp_remote_retrieve_body', array( 'return' => json_encode( $body ) ) );
    }

    /**
     * Expects the answer to be cached for pixel 123 and its token, for a
     * number of seconds: a day (86400), or an hour (3600) after an error.
     *
     * @param string $status Expected status of the cached answer.
     * @param int    $ttl    Expected lifetime.
     * @return void
     */
    private function expect_cached( $status, $ttl ) {
        \WP_Mock::userFunction(
            'set_transient',
            array(
                'times' => 1,
                'args'  => array(
                    LeadEventQuality::CACHE_KEY,
                    \Mockery::on(
                        function ( $value ) use ( $status ) {
                            return $this->fingerprint( '123', 'token' ) === $value['credentials']
                                && false === strpos( json_encode( $value ), 'token' )
                                && $status === $value['result']['status'];
                        }
                    ),
                    $ttl,
                ),
            )
        );
    }

    /**
     * Without a pixel or token Meta is not asked.
     *
     * @return void
     */
    public function testNotConnected() {
        \WP_Mock::userFunction( 'wp_remote_get', array( 'times' => 0 ) );
        \WP_Mock::userFunction( 'get_transient', array( 'times' => 0 ) );

        $this->assertSame( 'not_connected', ( new LeadEventQuality( '', 'token' ) )->get()['status'] );
        $this->assertSame( 'not_connected', ( new LeadEventQuality( '123', '' ) )->get()['status'] );
    }

    /**
     * The Lead event's score and parameters are read and cached for a day:
     * most included parameter first, unknown identifiers humanized.
     *
     * @return void
     */
    public function testFetchesAndCachesTheLeadEvent() {
        \WP_Mock::userFunction( 'get_transient', array( 'return' => false ) );
        $this->mock_http( 200, $this->response_body() );
        $this->expect_cached( 'ok', 86400 );

        $result = ( new LeadEventQuality( '123', 'token' ) )->get();

        $this->assertSame(
            array(
                'status'     => 'ok',
                'score'      => 7.3,
                'parameters' => array(
                    array(
                        'identifier' => 'ip_address',
                        'label'      => 'IP address',
                        'coverage'   => 100.0,
                    ),
                    array(
                        'identifier' => 'some_new_key',
                        'label'      => 'Some new key',
                        'coverage'   => 80.0,
                    ),
                    array(
                        'identifier' => 'email',
                        'label'      => 'Email',
                        'coverage'   => 62.5,
                    ),
                ),
                'message'    => '',
            ),
            $result
        );
    }

    /**
     * A cached answer for the same pixel and token is used without asking
     * Meta.
     *
     * @return void
     */
    public function testUsesTheCacheOfTheSameCredentials() {
        $cached = array(
            'status' => 'ok',
            'score'  => 9.0,
        );
        \WP_Mock::userFunction(
            'get_transient',
            array(
                'return' => array(
                    'credentials' => $this->fingerprint( '123', 'token' ),
                    'result'      => $cached,
                ),
            )
        );
        \WP_Mock::userFunction( 'wp_remote_get', array( 'times' => 0 ) );
        $this->assertSame( $cached, ( new LeadEventQuality( '123', 'token' ) )->get() );
    }

    /**
     * A cached answer for another pixel is replaced.
     *
     * @return void
     */
    public function testRefetchesWhenThePixelChanged() {
        \WP_Mock::userFunction(
            'get_transient',
            array(
                'return' => array(
                    'credentials' => $this->fingerprint( '999', 'token' ),
                    'result'      => array( 'status' => 'ok' ),
                ),
            )
        );
        $this->mock_http( 200, array( 'web' => array() ) );
        $this->expect_cached( 'no_data', 86400 );

        $this->assertSame( 'no_data', ( new LeadEventQuality( '123', 'token' ) )->get()['status'] );
    }

    /**
     * A score cached with another token of the same pixel is not kept: a
     * replaced token is asked again.
     *
     * @return void
     */
    public function testRefetchesWhenTheTokenChanged() {
        \WP_Mock::userFunction(
            'get_transient',
            array(
                'return' => array(
                    'credentials' => $this->fingerprint( '123', 'old-token' ),
                    'result'      => array(
                        'status' => 'ok',
                        'score'  => 9.0,
                    ),
                ),
            )
        );
        $this->mock_http( 403, array( 'error' => array( 'message' => 'Session has expired' ) ) );
        $this->expect_cached( 'error', 3600 );

        $this->assertSame( 'error', ( new LeadEventQuality( '123', 'token' ) )->get()['status'] );
    }

    /**
     * An error cached for an invalid token is not kept once the token is
     * repaired: the panel can show again at once.
     *
     * @return void
     */
    public function testRefetchesWhenAnInvalidTokenIsReplaced() {
        \WP_Mock::userFunction(
            'get_transient',
            array(
                'return' => array(
                    'credentials' => $this->fingerprint( '123', 'invalid-token' ),
                    'result'      => array( 'status' => 'error' ),
                ),
            )
        );
        $this->mock_http( 200, $this->response_body() );
        $this->expect_cached( 'ok', 86400 );

        $this->assertSame( 'ok', ( new LeadEventQuality( '123', 'token' ) )->get()['status'] );
    }

    /**
     * A cached answer in an unexpected shape is replaced, not trusted.
     *
     * @return void
     */
    public function testRefetchesWhenTheCacheIsMalformed() {
        \WP_Mock::userFunction(
            'get_transient',
            array(
                'return' => array(
                    'credentials' => array( 'not-a-string' ),
                    'result'      => array( 'status' => 'ok' ),
                ),
            )
        );
        $this->mock_http( 200, array( 'web' => array() ) );
        $this->expect_cached( 'no_data', 86400 );

        $this->assertSame( 'no_data', ( new LeadEventQuality( '123', 'token' ) )->get()['status'] );
    }

    /**
     * A Graph API error is reported with Meta's message and retried after an
     * hour.
     *
     * @return void
     */
    public function testGraphErrorIsCachedBriefly() {
        \WP_Mock::userFunction( 'get_transient', array( 'return' => false ) );
        $this->mock_http( 403, array( 'error' => array( 'message' => 'Missing permissions' ) ) );
        $this->expect_cached( 'error', 3600 );

        $result = ( new LeadEventQuality( '123', 'token' ) )->get();

        $this->assertSame( 'error', $result['status'] );
        $this->assertSame( 'Missing permissions', $result['message'] );
        $this->assertNull( $result['score'] );
    }

    /**
     * A failed request (WP_Error) is an error.
     *
     * @return void
     */
    public function testRequestFailureIsAnError() {
        \WP_Mock::userFunction( 'get_transient', array( 'return' => false ) );
        \WP_Mock::userFunction( 'add_query_arg', array( 'return' => 'https://graph.facebook.com/x' ) );
        // Stands in for a WP_Error (is_wp_error() is mocked).
        $error = new class() {
            /**
             * The error message.
             *
             * @return string
             */
            public function get_error_message() {
                return 'timed out';
            }
        };
        \WP_Mock::userFunction( 'wp_remote_get', array( 'return' => $error ) );
        \WP_Mock::userFunction( 'is_wp_error', array( 'return' => true ) );
        $this->expect_cached( 'error', 3600 );

        $result = ( new LeadEventQuality( '123', 'token' ) )->get();

        $this->assertSame( 'error', $result['status'] );
        $this->assertSame( 'timed out', $result['message'] );
    }

    /**
     * Without a scored Lead event there is no data; scores are kept in 0-10.
     *
     * @return void
     */
    public function testParse() {
        $this->assertSame( 'no_data', LeadEventQuality::parse( array() )['status'] );
        $this->assertSame(
            'no_data',
            LeadEventQuality::parse(
                array(
                    'web' => array(
                        array( 'event_name' => 'Lead' ),
                        array(
                            'event_name'          => 'Purchase',
                            'event_match_quality' => array( 'composite_score' => 8 ),
                        ),
                    ),
                )
            )['status']
        );

        $result = LeadEventQuality::parse(
            array(
                'web' => array(
                    array(
                        'event_name'          => 'Lead',
                        'event_match_quality' => array( 'composite_score' => 12 ),
                    ),
                ),
            )
        );
        $this->assertSame( 10.0, $result['score'] );
        $this->assertSame( array(), $result['parameters'] );
    }

    /**
     * Clearing the cache deletes the transient.
     *
     * @return void
     */
    public function testClearCache() {
        \WP_Mock::userFunction(
            'delete_transient',
            array(
                'times' => 1,
                'args'  => array( LeadEventQuality::CACHE_KEY ),
            )
        );
        LeadEventQuality::clear_cache();
        $this->assertConditionsMet();
    }
}
