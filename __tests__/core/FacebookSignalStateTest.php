<?php
/**
 * Facebook Pixel Plugin FacebookSignalStateTest class.
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

use FacebookPixelPlugin\Core\FacebookSignalState;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookSignalStateTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookSignalStateTest extends FacebookWordpressTestBase {

    public function setUp(): void {
        parent::setUp();
        FacebookSignalState::reset_queue();
    }

    public function tearDown(): void {
        unset( $_COOKIE[ FacebookSignalState::COOKIE_NAME ] );
        parent::tearDown();
    }

    public function testSetAndGetAttributionData() {
        FacebookSignalState::set_attribution_data( 'fbc', 'fb.1.123.abc' );
        FacebookSignalState::set_attribution_data( 'fbp', 'fb.1.456.def' );

        $this->assertEquals( 'fb.1.123.abc', FacebookSignalState::get_attribution_data( 'fbc' ) );
        $this->assertEquals( 'fb.1.456.def', FacebookSignalState::get_attribution_data( 'fbp' ) );
    }

    public function testGetAttributionDataReturnsNullForMissingKey() {
        $this->assertNull( FacebookSignalState::get_attribution_data( 'nonexistent' ) );
    }

    public function testSetAttributionDataOverwritesPreviousValue() {
        FacebookSignalState::set_attribution_data( 'fbc', 'old_value' );
        FacebookSignalState::set_attribution_data( 'fbc', 'new_value' );

        $this->assertEquals( 'new_value', FacebookSignalState::get_attribution_data( 'fbc' ) );
    }

    public function testResetQueueClearsAttributionData() {
        FacebookSignalState::set_attribution_data( 'fbc', 'fb.1.123.abc' );
        FacebookSignalState::set_attribution_data( 'fbp', 'fb.1.456.def' );

        FacebookSignalState::reset_queue();

        $this->assertNull( FacebookSignalState::get_attribution_data( 'fbc' ) );
        $this->assertNull( FacebookSignalState::get_attribution_data( 'fbp' ) );
    }

    public function testAttributionDataStoresDomains() {
        FacebookSignalState::set_attribution_data( 'fbp_domain', 'example.com' );
        FacebookSignalState::set_attribution_data( 'fbc_domain', 'example.com' );

        $this->assertEquals( 'example.com', FacebookSignalState::get_attribution_data( 'fbp_domain' ) );
        $this->assertEquals( 'example.com', FacebookSignalState::get_attribution_data( 'fbc_domain' ) );
    }

    public function testPauseAndResumeToggleState() {
        \WP_Mock::userFunction( 'is_ssl', array( 'return' => false ) );

        $this->assertFalse( FacebookSignalState::is_held() );

        FacebookSignalState::hold();
        $this->assertTrue( FacebookSignalState::is_held() );

        FacebookSignalState::release();
        $this->assertFalse( FacebookSignalState::is_held() );
    }

    public function testHoldSetsCookie() {
        \WP_Mock::userFunction( 'is_ssl', array( 'return' => false ) );

        FacebookSignalState::hold();

        $this->assertSame( 'held', $_COOKIE[ FacebookSignalState::COOKIE_NAME ] );
    }

    public function testReleaseSetsCookie() {
        \WP_Mock::userFunction( 'is_ssl', array( 'return' => false ) );

        FacebookSignalState::hold();
        FacebookSignalState::release();

        $this->assertSame( 'active', $_COOKIE[ FacebookSignalState::COOKIE_NAME ] );
    }

    public function testHeldFrontendRequestIsSuppressed() {
        $this->mockRequestContext( false, false, false );
        FacebookSignalState::hold();

        $this->assertTrue(
            FacebookSignalState::should_suppress_frontend_send()
        );
    }

    public function testHeldAdminAjaxRequestIsSuppressed() {
        $this->mockRequestContext( true, true, false );
        FacebookSignalState::hold();

        $this->assertTrue(
            FacebookSignalState::should_suppress_frontend_send()
        );
    }

    public function testHeldAdminScreenIsNotSuppressed() {
        $this->mockRequestContext( true, false, false );
        FacebookSignalState::hold();

        $this->assertFalse(
            FacebookSignalState::should_suppress_frontend_send()
        );
    }

    public function testHeldCronRequestIsNotSuppressed() {
        $this->mockRequestContext( false, false, true );
        FacebookSignalState::hold();

        $this->assertFalse(
            FacebookSignalState::should_suppress_frontend_send()
        );
    }

    /**
     * Mocks the WordPress request context used by the suppression helper.
     *
     * @param bool $is_admin Whether WordPress considers this an admin request.
     * @param bool $is_ajax  Whether WordPress considers this an AJAX request.
     * @param bool $is_cron  Whether WordPress considers this a cron request.
     * @return void
     */
    private function mockRequestContext( $is_admin, $is_ajax, $is_cron ) {
        \WP_Mock::userFunction( 'is_ssl', array( 'return' => false ) );
        \WP_Mock::userFunction( 'is_admin', array( 'return' => $is_admin ) );
        \WP_Mock::userFunction(
            'wp_doing_ajax',
            array( 'return' => $is_ajax )
        );
        \WP_Mock::userFunction(
            'wp_doing_cron',
            array( 'return' => $is_cron )
        );
    }
}
