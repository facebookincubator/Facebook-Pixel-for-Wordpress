<?php
/**
 * Facebook Pixel Plugin FacebookWordpressWPECommerceTest class.
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

namespace FacebookPixelPlugin\Tests\Integration;

use FacebookPixelPlugin\Core\Signals;
use FacebookPixelPlugin\Core\EventData;
use FacebookPixelPlugin\Core\EventDataBuilder;
use FacebookPixelPlugin\Integration\FacebookWordpressWPECommerce;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressWPECommerceTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressWPECommerceTest extends FacebookWordpressTestBase {

  /**
   * Builds a WP eCommerce integration with a mock Signals + real builder.
   *
   * @return array [ FacebookWordpressWPECommerce, Signals mock ]
   */
  private function makeIntegration() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressWPECommerce(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * inject_pixel_code registers the three WP eCommerce events.
   *
   * @return void
   */
  public function testInjectPixelCodeRegistersThreeEvents() {
    list( $integration, $signals ) = $this->makeIntegration();

    $signals->expects( $this->exactly( 3 ) )->method( 'on' );

    $integration->inject_pixel_code();

    $this->assertConditionsMet();
  }

  /**
   * read_purchase builds a Purchase EventData from the purchase log.
   *
   * @return void
   */
  public function testReadPurchaseReturnsEventData() {
    $utils = \Mockery::mock(
      'alias:FacebookPixelPlugin\Core\FacebookPluginUtils'
    );
    $utils->shouldReceive( 'get_logged_in_user_info' )->andReturn( array() );

    $item        = new \stdClass();
    $item->prodid = 7;
    $log          = \Mockery::mock();
    $log->shouldReceive( 'get_items' )->andReturn( array( $item ) );
    $log->shouldReceive( 'get_total' )->andReturn( 42.0 );

    list( $integration ) = $this->makeIntegration();

    $data = $integration->read_purchase( $log, null, true );

    $this->assertInstanceOf( EventData::class, $data );
    $fields = $data->to_array();
    $this->assertEquals( array( 7 ), $fields['content_ids'] );
    $this->assertEquals( 'product', $fields['content_type'] );
    $this->assertEquals( 42.0, $fields['value'] );
  }

  /**
   * read_purchase returns null when results are not displayed.
   *
   * @return void
   */
  public function testReadPurchaseSkipsWhenNotDisplayed() {
    list( $integration ) = $this->makeIntegration();
    $log                 = \Mockery::mock();

    $this->assertNull( $integration->read_purchase( $log, null, false ) );
  }

  /**
   * read_add_to_cart returns null when the response has no product id.
   *
   * @return void
   */
  public function testReadAddToCartSkipsWithoutProductId() {
    list( $integration ) = $this->makeIntegration();

    $this->assertNull( $integration->read_add_to_cart( array() ) );
  }
}
