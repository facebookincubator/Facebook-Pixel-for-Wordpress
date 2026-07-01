<?php
/**
 * Facebook Pixel Plugin EventDataTest class.
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
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * EventDataTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class EventDataTest extends FacebookWordpressTestBase {

    /**
     * to_array returns the fields verbatim.
     *
     * @return void
     */
    public function testToArray() {
        $fields = array(
            'email'      => 'a@b.com',
            'first_name' => 'Ada',
        );
        $this->assertEquals( $fields, ( new EventData( $fields ) )->to_array() );
    }

    /**
     * get returns values and the default for missing keys.
     *
     * @return void
     */
    public function testGet() {
        $data = new EventData( array( 'value' => 12.5 ) );
        $this->assertSame( 12.5, $data->get( 'value' ) );
        $this->assertNull( $data->get( 'missing' ) );
        $this->assertSame( 'x', $data->get( 'missing', 'x' ) );
    }

    /**
     * is_empty reflects whether any fields are present.
     *
     * @return void
     */
    public function testIsEmpty() {
        $this->assertTrue( ( new EventData() )->is_empty() );
        $this->assertFalse(
            ( new EventData( array( 'email' => 'a@b.com' ) ) )->is_empty()
        );
    }
}
