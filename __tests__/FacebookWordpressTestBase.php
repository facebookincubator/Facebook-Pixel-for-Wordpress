<?php
/**
 * Facebook Pixel Plugin FacebookWordpressTestBase class.
 *
 * This file contains the main logic for FacebookWordpressTestBase.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressTestBase class.
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

namespace FacebookPixelPlugin\Tests;

use PHPUnit\Framework\TestCase;
use WP_Mock\Tools\Constraints\ExpectationsMet;
use FacebookPixelPlugin\Core\AAMSettingsFields;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\AdsPixelSettings;
use FacebookPixelPlugin\Core\FacebookSignalState;

/**
 * FacebookWordpressTestBase class.
 */
abstract class FacebookWordpressTestBase extends TestCase {
    /**
     * Shared alias mock handle.
     *
     * @var mixed
     */
    protected $mocked_fbpixel;

    /**
     * Shared options alias mock handle.
     *
     * @var mixed
     */
    protected $mocked_options;

    /**
     * Sets up the environment for each test.
     *
     * Initializes WP_Mock, sets the global WordPress version,
     * configures Mockery constants, and sets server variables
     * to simulate an HTTPS request to 'www.pikachu.com'.
     *
     * @return void
     */
    public function setUp(): void {
        \WP_Mock::setUp();
        $GLOBALS['wp_version'] = '1.0';
    \Mockery::getConfiguration()->setConstantsMap(
        array(
            'FacebookPixelPlugin\Core\FacebookPixel' => array(
                'FB_INTEGRATION_TRACKING_KEY' => 'fb_integration_tracking',
            ),
        )
    );

        $_SERVER['HTTPS']       = 'on';
        $_SERVER['HTTP_HOST']   = 'www.pikachu.com';
        $_SERVER['REQUEST_URI'] = '/index.php';
        $_COOKIE                = array();
        $_POST                  = array();
        $_SESSION               = array();
        FacebookSignalState::resume();
    }

    /**
     * Cleans up the environment after each test.
     *
     * Asserts that all Mockery expectations have been met, unsets the global
     * WordPress version, and calls `tearDown` on WP_Mock.
     *
     * @return void
     */
    public function tearDown(): void {
    $this->addToAssertionCount(
        \Mockery::getContainer()->mockery_getExpectationCount()
    );
        unset( $GLOBALS['wp_version'] );
        \WP_Mock::tearDown();
    }

    protected function assertConditionsMet( $message = '' ) {
        $this->assertThat( null, new ExpectationsMet(), $message );
    }

    protected function assertHooksAdded() {
        $hooks_not_added = $expected_hooks = 0;
        try {
            \WP_Mock::assertHooksAdded();
        } catch ( \Exception $e ) {
            $hooks_not_added = 1;
            $expected_hooks  = $e->getMessage();
        }
        $this->assertEmpty( $hooks_not_added, (string) $expected_hooks );
    }

    /**
     * Mocks the return value of FacebookPluginUtils::isInternalUser().
     *
     * @param bool $is_internal_user Whether the user is an internal user.
     *
     * @return void
     */
    protected function mockIsInternalUser( $is_internal_user ) {
        $this->mocked_fbpixel =
        \Mockery::mock( 'alias:FacebookPixelPlugin\Core\FacebookPluginUtils' );
        $this->mocked_fbpixel->shouldReceive( 'is_internal_user' )
        ->andReturn( $is_internal_user );
    }

    /**
     * Mocks the return value of FacebookWordpressOptions methods.
     *
     * This function mocks the return values of the FacebookWordpressOptions
     * class methods. The $options parameter is an associative array
     * where the keys are method names and the values are the return values
     * for those methods. If a key is not provided, the method will return
     * a default value.
     *
     * @param array            $options An associative
     *                          array of method names and their
     *                                  return values.
     * @param AdsPixelSettings $aam_settings The return value for the
     *                                       get_aam_settings() method.
     */
    protected function mockFacebookWordpressOptions(
        $options = array(),
        $aam_settings = null
    ) {
    $this->mocked_options = \Mockery::mock(
        'alias:FacebookPixelPlugin\Core\FacebookWordpressOptions'
    );
    if ( array_key_exists( 'agent_string', $options ) ) {
        $this->mocked_options->shouldReceive( 'get_agent_string' )
        ->andReturn( $options['agent_string'] );
    } else {
        $this->mocked_options->shouldReceive( 'get_agent_string' )
                        ->andReturn( 'WordPress' );
    }
    if ( array_key_exists( 'pixel_id', $options ) ) {
        $this->mocked_options->shouldReceive( 'get_pixel_id' )
        ->andReturn( $options['pixel_id'] );
    } else {
        $this->mocked_options->shouldReceive( 'get_pixel_id' )
        ->andReturn( '1234' );
    }
    if ( array_key_exists( 'access_token', $options ) ) {
        $this->mocked_options->shouldReceive( 'get_access_token' )
        ->andReturn( $options['access_token'] );
    } else {
        $this->mocked_options->shouldReceive( 'get_access_token' )
        ->andReturn( 'abcd' );
    }
    if ( array_key_exists( 'is_fbe_installed', $options ) ) {
        $this->mocked_options->shouldReceive( 'get_is_fbe_installed' )
        ->andReturn( $options['is_fbe_installed'] );
    } else {
        $this->mocked_options->shouldReceive( 'get_is_fbe_installed' )
        ->andReturn( '0' );
    }
    if ( is_null( $aam_settings ) ) {
        $this->mocked_options->shouldReceive( 'get_aam_settings' )
        ->andReturn( $this->getDefaultAAMSettings() );
    } else {
        $this->mocked_options->shouldReceive( 'get_aam_settings' )
        ->andReturn( $aam_settings );
    }
        $this->mocked_options->shouldReceive( 'get_capi_pii_caching_status' )
                            ->andReturn( 0 );

    // FBL4B bridge methods — delegate to MBE values by default
    $active_pixel = array_key_exists( 'pixel_id', $options )
        ? $options['pixel_id'] : '1234';
    $active_token = array_key_exists( 'access_token', $options )
        ? $options['access_token'] : 'abcd';
    $this->mocked_options->shouldReceive( 'get_active_pixel_id' )
        ->andReturn( $active_pixel );
    $this->mocked_options->shouldReceive( 'get_active_access_token' )
        ->andReturn( $active_token );
    $this->mocked_options->shouldReceive( 'get_is_fbl4b_installed' )
        ->andReturn( false );
    $this->mocked_options->shouldReceive( 'get_connection_type' )
        ->andReturn( 'mbe' );
    }


    /**
     * Returns the default AdsPixelSettings object.
     *
     * The default AdsPixelSettings object returned by
     * this method has the pixel ID
     * set to '123', automatic matching enabled, and all
     * automatic matching fields
     * enabled.
     *
     * @return AdsPixelSettings The default AdsPixelSettings object.
     */
    protected function getDefaultAAMSettings() {
        $aam_settings = new AdsPixelSettings();
        $aam_settings->setPixelId( '123' );
        $aam_settings->setEnableAutomaticMatching( true );
        $aam_settings->setEnabledAutomaticMatchingFields( AAMSettingsFields::get_all_fields() );
        return $aam_settings;
    }
}
