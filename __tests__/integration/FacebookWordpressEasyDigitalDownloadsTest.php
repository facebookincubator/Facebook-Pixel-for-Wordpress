<?php
/**
 * Facebook Pixel Plugin FacebookWordpressEasyDigitalDownloadsTest class.
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
use FacebookPixelPlugin\Integration\FacebookWordpressEasyDigitalDownloads;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FacebookWordpressEasyDigitalDownloadsTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FacebookWordpressEasyDigitalDownloadsTest extends FacebookWordpressTestBase {

  /**
   * Builds an EDD integration with a mock Signals + real builder.
   *
   * @return array [ FacebookWordpressEasyDigitalDownloads, Signals mock ]
   */
  private function makeIntegration() {
    $signals     = $this->createMock( Signals::class );
    $integration = new FacebookWordpressEasyDigitalDownloads(
      $signals,
      new EventDataBuilder()
    );
    return array( $integration, $signals );
  }

  /**
   * Alias-mocks the plugin utils + EDD helpers used by the create* builders,
   * and stubs the WordPress functions the AddToCart/ViewContent path needs.
   *
   * @return void
   */
  private function setupProductMocks() {
    $utils = \Mockery::mock(
      'alias:FacebookPixelPlugin\Core\FacebookPluginUtils'
    );
    $utils->shouldReceive( 'get_logged_in_user_info' )->andReturn(
      array(
        'email'      => 'pika.chu@s2s.com',
        'first_name' => 'Pika',
        'last_name'  => 'Chu',
      )
    );

    $edd_utils = \Mockery::mock(
      'alias:FacebookPixelPlugin\Integration\EDDUtils'
    );
    $edd_utils->shouldReceive( 'get_currency' )->andReturn( 'USD' );
    $edd_utils->shouldReceive( 'get_cart_total' )->andReturn( 300 );

    $download             = new \stdClass();
    $download->post_title = 'Encarta';
    \WP_Mock::userFunction(
      'edd_get_download',
      array( 'return' => $download )
    );
    \WP_Mock::userFunction(
      'get_post_meta',
      array( 'return' => array( array( 'amount' => 50 ) ) )
    );
  }

  /**
   * inject_pixel_code registers the five Signals-routed EDD events.
   *
   * @return void
   */
  public function testInjectPixelCodeRegistersFiveSignalEvents() {
    list( $integration, $signals ) = $this->makeIntegration();

    $signals->expects( $this->exactly( 5 ) )->method( 'on' );

    \WP_Mock::expectActionAdded(
      'edd_purchase_link_top',
      array( $integration, 'inject_add_to_cart_event_id' )
    );

    $integration->inject_pixel_code();

    $this->assertConditionsMet();
  }

  /**
   * read_initiate_checkout builds an InitiateCheckout EventData.
   *
   * @return void
   */
  public function testReadInitiateCheckoutReturnsEventData() {
    \WP_Mock::userFunction( 'EDD' );
    $this->setupProductMocks();

    list( $integration ) = $this->makeIntegration();

    $data = $integration->read_initiate_checkout();

    $this->assertInstanceOf( EventData::class, $data );
    $fields = $data->to_array();
    $this->assertEquals( 'pika.chu@s2s.com', $fields['email'] );
    $this->assertEquals( 'USD', $fields['currency'] );
    $this->assertEquals( 300, $fields['value'] );
  }

  /**
   * read_initiate_checkout returns null when EDD is not loaded.
   *
   * @return void
   */
  public function testReadInitiateCheckoutSkipsWithoutEdd() {
    list( $integration ) = $this->makeIntegration();

    $this->assertNull( $integration->read_initiate_checkout() );
  }

  /**
   * read_purchase builds a Purchase EventData from the payment meta.
   *
   * @return void
   */
  public function testReadPurchaseReturnsEventData() {
    \WP_Mock::userFunction(
      'edd_get_payment_meta',
      array(
        'return' => array(
          'email'        => 'pika.chu@s2s.com',
          'user_info'    => array(
            'first_name' => 'Pika',
            'last_name'  => 'Chu',
          ),
          'cart_details' => array(
            array(
              'id'    => 99,
              'price' => 300,
            ),
            array(
              'id'    => 999,
              'price' => 400,
            ),
          ),
          'currency'     => 'USD',
        ),
      )
    );

    list( $integration ) = $this->makeIntegration();

    $payment     = new \stdClass();
    $payment->ID = 1;

    $data = $integration->read_purchase( $payment, null );

    $this->assertInstanceOf( EventData::class, $data );
    $fields = $data->to_array();
    $this->assertEquals( 'pika.chu@s2s.com', $fields['email'] );
    $this->assertEquals( 700, $fields['value'] );
    $this->assertEquals( 'USD', $fields['currency'] );
    $this->assertEquals( array( 99, 999 ), $fields['content_ids'] );
    $this->assertEquals( 'product', $fields['content_type'] );
  }

  /**
   * read_purchase returns null when the payment has no ID.
   *
   * @return void
   */
  public function testReadPurchaseSkipsWithoutPaymentId() {
    list( $integration ) = $this->makeIntegration();

    $payment     = new \stdClass();
    $payment->ID = 0;

    $this->assertNull( $integration->read_purchase( $payment, null ) );
  }

  /**
   * read_view_content builds a ViewContent EventData for a download.
   *
   * @return void
   */
  public function testReadViewContentReturnsEventData() {
    $this->setupProductMocks();

    list( $integration ) = $this->makeIntegration();

    $data = $integration->read_view_content( 1234 );

    $this->assertInstanceOf( EventData::class, $data );
    $fields = $data->to_array();
    $this->assertEquals( array( '1234' ), $fields['content_ids'] );
    $this->assertEquals( 'product', $fields['content_type'] );
    $this->assertEquals( 'USD', $fields['currency'] );
    $this->assertEquals( 'Encarta', $fields['content_name'] );
    $this->assertEquals( 50, $fields['value'] );
  }

  /**
   * read_view_content returns null without a download id.
   *
   * @return void
   */
  public function testReadViewContentSkipsWithoutDownloadId() {
    list( $integration ) = $this->makeIntegration();

    $this->assertNull( $integration->read_view_content( 0 ) );
  }

  /**
   * read_add_to_cart verifies the nonce, builds the EventData, and reuses the
   * shared event id from the posted form data.
   *
   * @return void
   */
  public function testReadAddToCartReturnsEventDataWithSharedId() {
    $this->setupProductMocks();

    $_POST['nonce']       = '54321';
    $_POST['download_id'] = '1234';
    $_POST['post_data']   = 'facebook_event_id=abc-123';

    \WP_Mock::userFunction(
      'absint',
      array( 'return' => 1234 )
    );
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );
    \WP_Mock::userFunction(
      'wp_verify_nonce',
      array( 'return' => true )
    );

    list( $integration ) = $this->makeIntegration();

    $data = $integration->read_add_to_cart();

    $this->assertInstanceOf( EventData::class, $data );
    $this->assertEquals( 'abc-123', $data->get_event_id() );
    $fields = $data->to_array();
    $this->assertEquals( array( '1234' ), $fields['content_ids'] );
    $this->assertEquals( 'product', $fields['content_type'] );
    $this->assertEquals( 'Encarta', $fields['content_name'] );
  }

  /**
   * read_add_to_cart returns null when the AJAX payload is incomplete.
   *
   * @return void
   */
  public function testReadAddToCartSkipsWithoutPost() {
    list( $integration ) = $this->makeIntegration();

    $this->assertNull( $integration->read_add_to_cart() );
  }

  /**
   * read_add_to_cart returns null when the nonce fails verification.
   *
   * @return void
   */
  public function testReadAddToCartSkipsOnBadNonce() {
    $_POST['nonce']       = 'bad';
    $_POST['download_id'] = '1234';
    $_POST['post_data']   = 'facebook_event_id=abc-123';

    \WP_Mock::userFunction(
      'absint',
      array( 'return' => 1234 )
    );
    \WP_Mock::userFunction(
      'sanitize_text_field',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );
    \WP_Mock::userFunction(
      'wp_verify_nonce',
      array( 'return' => false )
    );

    list( $integration ) = $this->makeIntegration();

    $this->assertNull( $integration->read_add_to_cart() );
  }

  /**
   * inject_add_to_cart_event_id prints the hidden shared-id field for a
   * non-internal user.
   *
   * @return void
   */
  public function testInjectAddToCartEventIdOutputsHiddenField() {
    self::mockIsInternalUser( false );
    \WP_Mock::userFunction(
      'esc_attr',
      array(
        'return' => function ( $input ) {
          return $input;
        },
      )
    );

    list( $integration ) = $this->makeIntegration();

    $integration->inject_add_to_cart_event_id();

    $this->expectOutputRegex(
      '/input type="hidden" name="facebook_event_id"/'
    );
  }

  /**
   * inject_add_to_cart_event_id prints nothing for an internal user.
   *
   * @return void
   */
  public function testInjectAddToCartEventIdSkipsInternalUser() {
    self::mockIsInternalUser( true );

    list( $integration ) = $this->makeIntegration();

    $integration->inject_add_to_cart_event_id();

    $this->expectOutputString( '' );
  }

  /**
   * inject_add_to_cart_listener enqueues nothing for an internal user.
   *
   * @return void
   */
  public function testInjectAddToCartListenerSkipsInternalUser() {
    self::mockIsInternalUser( true );

    list( $integration ) = $this->makeIntegration();

    $integration->inject_add_to_cart_listener( 1234 );

    $this->expectOutputString( '' );
  }
}
