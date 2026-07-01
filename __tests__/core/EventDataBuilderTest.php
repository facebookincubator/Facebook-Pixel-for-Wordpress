<?php
/**
 * Facebook Pixel Plugin EventDataBuilderTest class.
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

use FacebookPixelPlugin\Core\EventData;
use FacebookPixelPlugin\Core\EventDataBuilder;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * EventDataBuilderTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class EventDataBuilderTest extends FacebookWordpressTestBase {

    /**
     * build returns an EventData containing the provided fields.
     *
     * @return void
     */
    public function testBuildReturnsEventData() {
        $data = ( new EventDataBuilder() )->build(
            array(
                'email'      => 'a@b.com',
                'first_name' => 'Ada',
            )
        );

        $this->assertInstanceOf( EventData::class, $data );
        $this->assertEquals(
            array(
                'email'      => 'a@b.com',
                'first_name' => 'Ada',
            ),
            $data->to_array()
        );
    }

    /**
     * build drops null and empty-string values but keeps 0 and '0'.
     *
     * @return void
     */
    public function testBuildDropsNullAndEmpty() {
        $data = ( new EventDataBuilder() )->build(
            array(
                'email'      => 'a@b.com',
                'first_name' => null,
                'last_name'  => '',
                'value'      => 0,
                'num_items'  => '0',
            )
        );

        $this->assertEquals(
            array(
                'email'     => 'a@b.com',
                'value'     => 0,
                'num_items' => '0',
            ),
            $data->to_array()
        );
    }

    /**
     * build with no usable fields yields an empty EventData.
     *
     * @return void
     */
    public function testBuildEmpty() {
        $data = ( new EventDataBuilder() )->build(
            array(
                'email' => null,
                'phone' => '',
            )
        );

        $this->assertTrue( $data->is_empty() );
    }
}
