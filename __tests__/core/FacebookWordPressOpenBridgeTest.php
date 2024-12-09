<?php
/**
 * Facebook Pixel Plugin FacebookWordPressOpenBridgeTest class.
 *
 * This file contains the main logic for FacebookWordPressOpenBridgeTest.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordPressOpenBridgeTest class.
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

use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\FacebookWordpressOpenBridge;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordPressOpenBridgeTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class FacebookWordPressOpenBridgeTest extends FacebookWordpressTestBase {

    /**
     * Test that when no logged in user is present, a new GUID is generated.
     *
     * This test verifies that the FacebookWordpressOpenBridge class correctly
     * generates a new GUID and stores it in the session when no logged in
     * user is present. It also verifies that the GUID is properly returned
     * in the external_id field of the server event.
     */
    public function testWhenNoLoggedInUserNewGUIDShouldBeGenerated() {
        self::mockFacebookWordpressOptions();
        \WP_Mock::userFunction(
            'wp_get_current_user',
            array( 'return' => array() )
        );
        \WP_Mock::userFunction( 'get_current_user_id', array( 'return' => 0 ) );
        \WP_Mock::userFunction(
            'com_create_guid',
            array( 'return' => 'GUID' )
        );
        $_SESSION['obeid'] = 'GUID';

    \WP_Mock::userFunction(
        'sanitize_text_field',
        array(
            'args'   => array( \Mockery::any() ),
            'return' => function ( $input ) {
                return $input;
            },
        )
    );

        $event                = ServerEventFactory::new_event( 'Lead' );
        $open_bridge_instance = FacebookWordpressOpenBridge::get_instance();

        $ev = $open_bridge_instance->extract_from_databag( $event );

        $this->assertEquals( array( 'GUID' ), $ev['external_id'] );
    }

    /**
     * Sets up mock user and billing address data for testing.
     *
     * This method uses WP_Mock to simulate the return values for various
     * WordPress functions related to the current user and their billing
     * address. It mocks `wp_get_current_user`, `get_current_user_id`,
     * and `get_user_meta` calls to provide predefined user information
     * and billing address data for testing purposes.
     *
     * @return void
     */
    private function setupCustomerBillingAddress() {
    \WP_Mock::userFunction(
        'wp_get_current_user',
        array(
            'return' => (object) array(
                'ID'             => 1,
                'user_email'     => 'foo@foo.com',
                'user_firstname' => 'John',
                'user_lastname'  => 'Doe',
            ),
        )
    );
    \WP_Mock::userFunction(
        'get_current_user_id',
        array( 'return' => 1 )
    );
    \WP_Mock::userFunction(
        'get_user_meta',
        array(
            'times'  => 1,
            'args'   => array(
                \WP_Mock\Functions::type( 'int' ),
                'billing_city',
                true,
            ),
            'return' => 'Springfield',
        )
    );
    \WP_Mock::userFunction(
        'get_user_meta',
        array(
            'times'  => 1,
            'args'   => array(
                \WP_Mock\Functions::type( 'int' ),
                'billing_state',
                true,
            ),
            'return' => 'Ohio',
        )
    );
    \WP_Mock::userFunction(
        'get_user_meta',
        array(
            'times'  => 1,
            'args'   => array(
                \WP_Mock\Functions::type( 'int' ),
                'billing_postcode',
                true,
            ),
            'return' => '12345',
        )
    );
    \WP_Mock::userFunction(
        'get_user_meta',
        array(
            'times'  => 1,
            'args'   => array(
                \WP_Mock\Functions::type( 'int' ),
                'billing_country',
                true,
            ),
            'return' => 'US',
        )
    );
    \WP_Mock::userFunction(
        'get_user_meta',
        array(
            'times'  => 1,
            'args'   => array(
                \WP_Mock\Functions::type( 'int' ),
                'billing_phone',
                true,
            ),
            'return' => '2062062006',
        )
    );
    }

    /**
     * Test that external_id is fetched from
     * the cookie when obeid exists in the databag.
     *
     * @covers FacebookWordpressOpenBridge::extract_from_databag
     */
    public function testExternalIdFetchedFromCookieWhenObeidExists() {
        self::mockFacebookWordpressOptions();
        \WP_Mock::userFunction(
            'wp_get_current_user',
            array( 'return' => array() )
        );
        \WP_Mock::userFunction(
            'get_current_user_id',
            array( 'return' => 0 )
        );
        $_SESSION['obeid'] = 'testObeid';

    \WP_Mock::userFunction(
        'sanitize_text_field',
        array(
            'args'   => array( \Mockery::any() ),
            'return' => function ( $input ) {
                return $input;
            },
        )
    );

        $event                = ServerEventFactory::new_event( 'Lead' );
        $open_bridge_instance = FacebookWordpressOpenBridge::get_instance();

        $ev = $open_bridge_instance->extract_from_databag( $event );

        $this->assertEquals( array( 'testObeid' ), $ev['external_id'] );
    }

    /**
     * Test that external_id is fetched from the user ID if it's the first time.
     *
     * This test ensures that when the user ID is available,
     * it is included in the external_id array
     * along with the obeid from the session.
     *
     * @covers FacebookWordpressOpenBridge::extract_from_databag
     */
    public function testExternalIdFetchedFromUserIdIfFirstTime() {
        self::mockFacebookWordpressOptions();
        $this->setupCustomerBillingAddress();
        $_SESSION['obeid'] = 'testObeid';

    \WP_Mock::userFunction(
        'sanitize_text_field',
        array(
            'args'   => array( \Mockery::any() ),
            'return' => function ( $input ) {
                    return $input;
            },
        )
    );

        $event                = ServerEventFactory::new_event( 'Lead' );
        $open_bridge_instance = FacebookWordpressOpenBridge::get_instance();

        $ev = $open_bridge_instance->extract_from_databag( $event );

        $this->assertEquals( array( '1', 'testObeid' ), $ev['external_id'] );
    }

    /**
     * Test that external_id is fetched from AAM if it's the first time
     * and the user ID is not found.
     *
     * This test ensures that when the user ID is unavailable,
     * the external_id is sourced from the advanced matching settings (AAM)
     * and combined with the obeid from the session.
     *
     * @covers FacebookWordpressOpenBridge::extract_from_databag
     */
    public function testExternalIdFetchedFromAAMIfFirstTimeAndUserIdNotFound() {
        self::mockFacebookWordpressOptions();
        \WP_Mock::userFunction(
            'wp_get_current_user',
            array( 'return' => array() )
        );
        \WP_Mock::userFunction( 'get_current_user_id', array( 'return' => 0 ) );
        $_SESSION['obeid'] = 'testObeid';

    \WP_Mock::userFunction(
        'sanitize_text_field',
        array(
            'args'   => array( \Mockery::any() ),
            'return' => function ( $input ) {
                return $input;
            },
        )
    );

        $open_bridge_instance = FacebookWordpressOpenBridge::get_instance();
        $event                = array(
            'fb.advanced_matching' => array(
                'external_id' => 'testAM',
            ),
        );

        $ev = $open_bridge_instance->extract_from_databag( $event );

        $this->assertEquals(
            array( 'testAM', 'testObeid' ),
            $ev['external_id']
        );
    }
}
