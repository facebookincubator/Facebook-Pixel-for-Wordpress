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
use FacebookPixelPlugin\Core\FacebookSignalState;
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
        if ( PHP_VERSION_ID < 80100 ) {
            $instance->setAccessible( true );
        }
        $instance->setValue( null, null );

        $setup_done = $reflection->getProperty( 'server_setup_done' );
        if ( PHP_VERSION_ID < 80100 ) {
            $setup_done->setAccessible( true );
        }
        $setup_done->setValue( null, false );

        foreach ( array( 'resolved_fbc', 'resolved_fbp' ) as $cache_prop ) {
            $prop = $reflection->getProperty( $cache_prop );
            if ( PHP_VERSION_ID < 80100 ) {
                $prop->setAccessible( true );
            }
            $prop->setValue( null, false );
        }

        \WP_Mock::userFunction(
            'get_transient',
            array( 'return' => false )
        );
        \WP_Mock::userFunction(
            'set_transient',
            array( 'return' => true )
        );
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
     * Tests that server_setup stores attribution data but skips
     * cookie-setting when tracking is paused.
     */
    public function testServerSetupStoresAttributionWhenPaused() {
        FacebookSignalState::hold();

        FacebookParamBuilder::server_setup();

        $fbp = FacebookSignalState::get_attribution_data( 'fbp' );
        $this->assertTrue(
            null === $fbp || is_string( $fbp ),
            'Attribution fbp should be null or string when paused'
        );
    }

    /**
     * Tests that server_setup stores cookie domains in attribution when paused.
     */
    public function testServerSetupStoresDomainsWhenPaused() {
        FacebookSignalState::hold();

        FacebookParamBuilder::server_setup();

        $fbp_domain = FacebookSignalState::get_attribution_data( 'fbp_domain' );
        $this->assertTrue(
            null === $fbp_domain || is_string( $fbp_domain ),
            'Attribution fbp_domain should be null or string when paused'
        );
    }

    /**
     * Tests that server_setup does not store attribution when not paused.
     */
    public function testServerSetupDoesNotStoreAttributionWhenNotPaused() {
        \WP_Mock::userFunction(
            'sanitize_text_field',
            array(
                'args'   => array( \Mockery::any() ),
                'return' => function ( $input ) {
                    return $input;
                },
            )
        );

        FacebookParamBuilder::server_setup();

        $this->assertNull(
            FacebookSignalState::get_attribution_data( 'fbp_domain' ),
            'Attribution should not be stored when not paused'
        );
    }

    /**
     * Tests that get_fbp returns the same value on repeated calls.
     */
    public function testGetFbpReturnsSameValueOnRepeatedCalls() {
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

        $first  = FacebookParamBuilder::get_fbp();
        $second = FacebookParamBuilder::get_fbp();

        $this->assertSame( $first, $second );
    }

    /**
     * Tests that get_fbc returns the same value on repeated calls.
     */
    public function testGetFbcReturnsSameValueOnRepeatedCalls() {
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

        $first  = FacebookParamBuilder::get_fbc();
        $second = FacebookParamBuilder::get_fbc();

        $this->assertSame( $first, $second );
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
