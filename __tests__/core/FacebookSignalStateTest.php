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
}
