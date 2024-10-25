<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase WordPress.Files.FileName.InvalidClassFileName
/**
 * Facebook Pixel Plugin FacebookWordpressWooCommerce class.
 *
 * This file contains the main logic for FacebookWordpressWooCommerce.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressWooCommerce class.
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

namespace FacebookPixelPlugin\Integration;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

use FacebookPixelPlugin\Core\FacebookPixel;
use FacebookPixelPlugin\Core\FacebookPluginUtils;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Core\FacebookServerSideEvent;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookAds\Object\ServerSide\Content;

/**
 * FacebookWordpressWooCommerce class.
 */
class FacebookWordpressWooCommerce extends FacebookWordpressIntegrationBase {
	const PLUGIN_FILE   = 'facebook-for-woocommerce/facebook-for-woocommerce.php';
	const TRACKING_NAME = 'woocommerce';

	const FB_ID_PREFIX = 'wc_post_id_';

	const DIV_ID_FOR_AJAX_PIXEL_EVENTS = 'fb-pxl-ajax-code';

	/**
	 * Injects Facebook Pixel events for WooCommerce.
	 *
	 * This method sets up WordPress actions to inject Facebook Pixel events
	 * for different stages of the WooCommerce process, only if the
	 * Facebook for WooCommerce plugin is not active.
	 *
	 * - InitiateCheckout: Injects pixel event after checkout form.
	 * - AddToCart: Tracks add to cart actions.
	 * - Purchase: Fires pixel event on purchase completion.
	 * - ViewContent: Tracks product page views.
	 * - AJAX: Adds a footer div for AJAX-triggered events.
	 *
	 * Hooks are added with a priority of 40, and the add to cart event
	 * includes four parameters.
	 *
	 * @return void
	 */
	public static function injectPixelCode() {
		if ( ! self::isFacebookForWooCommerceActive() ) {
			add_action(
				'woocommerce_after_checkout_form',
				array( __CLASS__, 'trackInitiateCheckout' ),
				40
			);

			add_action(
				'woocommerce_add_to_cart',
				array( __CLASS__, 'trackAddToCartEvent' ),
				40,
				4
			);

			add_action(
				'woocommerce_thankyou',
				array( __CLASS__, 'trackPurchaseEvent' ),
				40
			);

			add_action(
				'woocommerce_payment_complete',
				array( __CLASS__, 'trackPurchaseEvent' ),
				40
			);

			add_action(
				'woocommerce_after_single_product',
				array( __CLASS__, 'trackViewContentEvent' ),
				40
			);

			add_action(
				'wp_footer',
				array( __CLASS__, 'addDivForAjaxPixelEvent' )
			);
		}
	}

	/**
	 * Injects a hidden div with an id of 'fb-pxl-ajax-code' into the page footer.
	 * This div is used to inject pixel events via AJAX requests.
	 */
	public static function addDivForAjaxPixelEvent() {
		echo wp_kses(
			self::getDivForAjaxPixelEvent(),
			array(
				'div' => array(
					'id' => array(),
				),
			)
		);
	}

	/**
	 * Generates a div element with a specific ID for AJAX-triggered pixel events.
	 *
	 * This method returns a div element with the ID defined by DIV_ID_FOR_AJAX_PIXEL_EVENTS.
	 * The function allows for optional content to be included inside the div.
	 *
	 * @param string $content Optional content to be placed inside the div.
	 * @return string HTML div element as a string.
	 */
	public static function getDivForAjaxPixelEvent( $content = '' ) {
		return "<div id='" . self::DIV_ID_FOR_AJAX_PIXEL_EVENTS . "'>"
		. $content . '</div>';
	}

	/**
	 * Injects a ViewContent event into the page, but only if the user is not an
	 * internal user (i.e. an admin user).
	 *
	 * The event is only injected if a valid product object can be retrieved from
	 * the current post ID. If not, the method simply exits.
	 *
	 * The ViewContent event is generated by calling the createViewContentEvent method
	 * and passing in the product object as an argument. The event is then tracked
	 * using the FacebookServerSideEvent singleton, and the pixel code is enqueued
	 * for output.
	 *
	 * @return void
	 */
	public static function trackViewContentEvent() {
		if ( FacebookPluginUtils::isInternalUser() ) {
			return;
		}

		global $post;
		if ( ! isset( $post->ID ) ) {
			return;
		}

		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return;
		}

		$server_event = ServerEventFactory::safeCreateEvent(
			'ViewContent',
			array( __CLASS__, 'createViewContentEvent' ),
			array( $product ),
			self::TRACKING_NAME
		);

		FacebookServerSideEvent::getInstance()->track( $server_event, false );

		self::enqueuePixelCode( $server_event );
	}

	/**
	 * Generates a ViewContent event data.
	 *
	 * The ViewContent event is generated by setting fields such as content_type,
	 * currency, value, content_ids, content_name and content_category.
	 *
	 * @param WC_Product $product Product object.
	 * @return array The event data.
	 */
	public static function createViewContentEvent( $product ) {
		$event_data = self::getPIIFromSession();

		$product_id   = self::getProductId( $product );
		$content_type = $product->is_type( 'variable' ) ? 'product_group' : 'product';

		$event_data['content_type']     = $content_type;
		$event_data['currency']         = \get_woocommerce_currency();
		$event_data['value']            = $product->get_price();
		$event_data['content_ids']      = array( $product_id );
		$event_data['content_name']     = $product->get_title();
		$event_data['content_category'] =
		self::getProductCategory( $product->get_id() );

		return array_filter( $event_data );
	}

	/**
	 * Returns the first category name of a given product ID.
	 *
	 * This method gets all the categories associated with the given product ID
	 * and returns the first category name. If no categories are associated with
	 * the product, the method returns null.
	 *
	 * @param int $product_id Product ID.
	 * @return string|null First category name associated with the product, or null.
	 */
	private static function getProductCategory( $product_id ) {
		$categories = get_the_terms(
			$product_id,
			'product_cat'
		);
		return count( $categories ) > 0 ? $categories[0]->name : null;
	}

	/**
	 * Tracks a Meta Pixel Purchase event.
	 *
	 * This method is a callback for the `woocommerce_thankyou` action hook.
	 * It tracks a Meta Pixel Purchase event whenever a purchase is completed.
	 *
	 * @param int $order_id The order ID.
	 *
	 * @since 1.0.0
	 */
	public static function trackPurchaseEvent( $order_id ) {
		if ( FacebookPluginUtils::isInternalUser() ) {
			return;
		}

		$server_event = ServerEventFactory::safeCreateEvent(
			'Purchase',
			array( __CLASS__, 'createPurchaseEvent' ),
			array( $order_id ),
			self::TRACKING_NAME
		);

		FacebookServerSideEvent::getInstance()->track( $server_event );

		self::enqueuePixelCode( $server_event );
	}

	/**
	 * Generates a Meta Pixel Purchase event data.
	 *
	 * The Purchase event is fired when a customer completes a purchase.
	 * It is typically sent when a customer submits an order.
	 *
	 * The method loops through the items in the order and creates a
	 * Meta Pixel Content object for each item. The method then sets the
	 * content_type, currency, value, content_ids and contents fields in
	 * the event data.
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return array The event data.
	 *
	 * @since 1.0.0
	 */
	public static function createPurchaseEvent( $order_id ) {
		$order = wc_get_order( $order_id );

		$content_type = 'product';
		$product_ids  = array();
		$contents     = array();

		foreach ( $order->get_items() as $item ) {
			$product = wc_get_product( $item->get_product_id() );
			if ( 'product_group' !== $content_type
			&& $product->is_type( 'variable' ) ) {
				$content_type = 'product_group';
			}

			$quantity   = $item->get_quantity();
			$product_id = self::getProductId( $product );

			$content = new Content();
			$content->setProductId( $product_id );
			$content->setQuantity( $quantity );
			$content->setItemPrice( $item->get_total() / $quantity );

			$contents[]    = $content;
			$product_ids[] = $product_id;
		}

		$event_data                 = self::getPiiFromBillingInformation( $order );
		$event_data['content_type'] = $content_type;
		$event_data['currency']     = \get_woocommerce_currency();
		$event_data['value']        = $order->get_total();
		$event_data['content_ids']  = $product_ids;
		$event_data['contents']     = $contents;

		return $event_data;
	}

	/**
	 * Generates a Meta Pixel AddToCart event data.
	 *
	 * The AddToCart event is fired when a customer adds a product to their cart.
	 * It is typically sent when a customer submits a form to add a product to their cart.
	 *
	 * The method loops through the items in the cart and creates a
	 * Meta Pixel Content object for each item. The method then sets the
	 * content_type, currency, value, content_ids and contents fields in
	 * the event data.
	 *
	 * @param string $cart_item_key The cart item key.
	 * @param int    $product_id    The product ID.
	 * @param int    $quantity      The quantity of the item in the cart.
	 * @param int    $variation_id  The variation ID.
	 *
	 * @since 1.0.0
	 */
	public static function trackAddToCartEvent(
		$cart_item_key,
		$product_id,
		$quantity,
		$variation_id
	) {
		if ( FacebookPluginUtils::isInternalUser() ) {
			return;
		}

		$server_event = ServerEventFactory::safeCreateEvent(
			'AddToCart',
			array( __CLASS__, 'createAddToCartEvent' ),
			array( $cart_item_key, $product_id, $quantity ),
			self::TRACKING_NAME
		);

		$is_ajax_request = wp_doing_ajax();

		FacebookServerSideEvent::getInstance()->track(
			$server_event,
			$is_ajax_request
		);

		if ( ! $is_ajax_request ) {
			self::enqueuePixelCode( $server_event );
		} else {
			FacebookServerSideEvent::getInstance()->setPendingPixelEvent(
				'addPixelCodeToAddToCartFragment',
				$server_event
			);
			add_filter(
				'woocommerce_add_to_cart_fragments',
				array( __CLASS__, 'addPixelCodeToAddToCartFragment' )
			);
		}
	}

	/**
	 * Modifies the WooCommerce "add to cart" AJAX fragment response
	 * to include the Meta Pixel code.
	 *
	 * This method is used to inject the Meta Pixel code into the
	 * page after the user has added a product to their cart.
	 *
	 * @param array $fragments The response array passed to the
	 *                          "woocommerce_add_to_cart_fragments" filter.
	 *
	 * @return array The modified response array.
	 *
	 * @since 1.0.0
	 */
	public static function addPixelCodeToAddToCartFragment( $fragments ) {
		$server_event =
		FacebookServerSideEvent::getInstance()
		->getPendingPixelEvent( 'addPixelCodeToAddToCartFragment' );
		if ( ! is_null( $server_event ) ) {
			$pixel_code = self::generatePixelCode( $server_event, true );
			$fragments[ '#' . self::DIV_ID_FOR_AJAX_PIXEL_EVENTS ] =
			self::getDivForAjaxPixelEvent( $pixel_code );
		}
		return $fragments;
	}

	/**
	 * Creates a Meta Pixel AddToCart event data.
	 *
	 * The AddToCart event is fired when a customer adds a product to their
	 * cart. It is typically sent when a customer adds a product to their
	 * cart.
	 *
	 * @param string $cart_item_key The cart item key.
	 * @param int    $product_id    The product ID.
	 * @param int    $quantity      The quantity.
	 *
	 * @return array The event data.
	 *
	 * @since 1.0.0
	 */
	public static function createAddToCartEvent(
		$cart_item_key,
		$product_id,
		$quantity
	) {
		$event_data                 = self::getPIIFromSession();
		$event_data['content_type'] = 'product';
		$event_data['currency']     = \get_woocommerce_currency();

		$cart_item = self::getCartItem( $cart_item_key );
		if ( ! empty( $cart_item_key ) ) {
			$event_data['content_ids'] =
			array( self::getProductId( $cart_item['data'] ) );
			$event_data['value']       = self::getAddToCartValue( $cart_item, $quantity );
		}

		return $event_data;
	}

	/**
	 * Tracks a Meta Pixel InitiateCheckout event.
	 *
	 * This method is a wrapper of FacebookServerSideEvent::track() method.
	 * It creates a Meta Pixel InitiateCheckout event data with the data
	 * from the WooCommerce session, and then tracks the event.
	 *
	 * @since 1.0.0
	 */
	public static function trackInitiateCheckout() {
		if ( FacebookPluginUtils::isInternalUser() ) {
			return;
		}

		$server_event = ServerEventFactory::safeCreateEvent(
			'InitiateCheckout',
			array( __CLASS__, 'createInitiateCheckoutEvent' ),
			array(),
			self::TRACKING_NAME
		);

		FacebookServerSideEvent::getInstance()->track( $server_event );

		self::enqueuePixelCode( $server_event );
	}

	/**
	 * Creates a Meta Pixel InitiateCheckout event data.
	 *
	 * The InitiateCheckout event is triggered when a customer initiates the checkout process.
	 * This method gathers personal identifiable information (PII) from the session and
	 * retrieves cart details such as the number of items, total value, content IDs,
	 * and contents of the cart. The event data is then returned for tracking purposes.
	 *
	 * @return array The event data including user PII, cart details, and currency information.
	 *
	 * @since 1.0.0
	 */
	public static function createInitiateCheckoutEvent() {
		$event_data                 = self::getPIIFromSession();
		$event_data['content_type'] = 'product';
		$event_data['currency']     = \get_woocommerce_currency();

		if ( WC()->cart ) {
			$cart = WC()->cart;

			$event_data['num_items']   = $cart->get_cart_contents_count();
			$event_data['value']       = $cart->total;
			$event_data['content_ids'] = self::getContentIds( $cart );
			$event_data['contents']    = self::getContents( $cart );
		}

		return $event_data;
	}

	/**
	 * Retrieves personally identifiable information (PII) from a WooCommerce order.
	 *
	 * This method extracts billing details from the given WooCommerce order object,
	 * including the first name, last name, email, postal code, state, country, city,
	 * and phone number. The PII is returned as an associative array.
	 *
	 * @param WC_Order $order The WooCommerce order object containing billing information.
	 *
	 * @return array An associative array containing the extracted PII.
	 *
	 * @since 1.0.0
	 */
	private static function getPiiFromBillingInformation( $order ) {
		$pii = array();

		$pii['first_name'] = $order->get_billing_first_name();
		$pii['last_name']  = $order->get_billing_last_name();
		$pii['email']      = $order->get_billing_email();
		$pii['zip']        = $order->get_billing_postcode();
		$pii['state']      = $order->get_billing_state();
		$pii['country']    = $order->get_billing_country();
		$pii['city']       = $order->get_billing_city();
		$pii['phone']      = $order->get_billing_phone();

		return $pii;
	}

	/**
	 * Calculates the total value for adding a specified quantity of a cart item to the cart.
	 *
	 * This method computes the total cost based on the line total and quantity of the provided
	 * cart item, and multiplies it by the specified quantity.
	 *
	 * @param array $cart_item An associative array representing the cart item, containing
	 *                         'line_total' and 'quantity' keys among others.
	 * @param int   $quantity  The quantity of the item to calculate the total value for.
	 *
	 * @return float|null The calculated total value for the specified quantity of the item,
	 *                    or null if the cart item is empty.
	 */
	private static function getAddToCartValue( $cart_item, $quantity ) {
		if ( ! empty( $cart_item ) ) {
			$price = $cart_item['line_total'] / $cart_item['quantity'];
			return $quantity * $price;
		}

		return null;
	}

	/**
	 * Retrieves a cart item from the WooCommerce cart by its key.
	 *
	 * This method accesses the current WooCommerce cart and returns the
	 * cart item associated with the provided cart item key. If the cart
	 * or the specified cart item is not found, the method returns null.
	 *
	 * @param string $cart_item_key The key for identifying the cart item.
	 *
	 * @return array|null An associative array representing the cart item,
	 *                    or null if the cart item is not found.
	 */
	private static function getCartItem( $cart_item_key ) {
		if ( WC()->cart ) {
			$cart = WC()->cart->get_cart();
			if ( ! empty( $cart ) && ! empty( $cart[ $cart_item_key ] ) ) {
				return $cart[ $cart_item_key ];
			}
		}

		return null;
	}

	/**
	 * Retrieves an array of product IDs from the given WooCommerce cart object.
	 *
	 * @param WC_Cart $cart The WooCommerce cart object.
	 *
	 * @return array An array of product IDs.
	 *
	 * @since 1.0.0
	 */
	private static function getContentIds( $cart ) {
		$product_ids = array();
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['data'] ) ) {
				$product_ids[] = self::getProductId( $item['data'] );
			}
		}

		return $product_ids;
	}

	/**
	 * Retrieves an array of Content objects from the given WooCommerce cart object.
	 *
	 * Each Content object represents an item in the cart and includes
	 * the product ID, quantity, and item price.
	 *
	 * @param WC_Cart $cart The WooCommerce cart object.
	 *
	 * @return Content[] An array of Content objects representing the items in the cart.
	 *
	 * @since 1.0.0
	 */
	private static function getContents( $cart ) {
		$contents = array();
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['data'] ) && ! empty( $item['quantity'] ) ) {
				$content = new Content();
				$content->setProductId( self::getProductId( $item['data'] ) );
				$content->setQuantity( $item['quantity'] );
				$content->setItemPrice( $item['line_total'] / $item['quantity'] );

				$contents[] = $content;
			}
		}

		return $contents;
	}

	/**
	 * Retrieves a unique product ID from the given WooCommerce product object.
	 *
	 * If the product has a SKU, the ID is in the format of "sku_woo_id".
	 * Otherwise, the ID is in the format of "fb_woo_id" where "fb_" is a prefix.
	 *
	 * @param WC_Product $product The WooCommerce product object.
	 *
	 * @return string The unique product ID.
	 *
	 * @since 1.0.0
	 */
	private static function getProductId( $product ) {
		$woo_id = $product->get_id();

		return $product->get_sku() ?
		$product->get_sku() . '_' . $woo_id
		: self::FB_ID_PREFIX . $woo_id;
	}

	/**
	 * Retrieves PII from the logged in user's session.
	 *
	 * @return array The user's PII data.
	 *
	 * @since 1.0.0
	 */
	private static function getPIIFromSession() {
		$event_data = FacebookPluginUtils::getLoggedInUserInfo();
		$user_id    = get_current_user_id();
		if ( 0 !== $user_id ) {
			$event_data['city']    = get_user_meta( $user_id, 'billing_city', true );
			$event_data['zip']     = get_user_meta( $user_id, 'billing_postcode', true );
			$event_data['country'] = get_user_meta( $user_id, 'billing_country', true );
			$event_data['state']   = get_user_meta( $user_id, 'billing_state', true );
			$event_data['phone']   = get_user_meta( $user_id, 'billing_phone', true );
		}
		return array_filter( $event_data );
	}

	/**
	 * Checks if Facebook for WooCommerce plugin is active.
	 *
	 * @return bool True if Facebook for WooCommerce is active, false otherwise.
	 *
	 * @since 1.0.0
	 */
	private static function isFacebookForWooCommerceActive() {
		return in_array(
			'facebook-for-woocommerce/facebook-for-woocommerce.php',
			get_option( 'active_plugins' ),
			true
		);
	}

	/**
	 * Generates the pixel code for a given server event.
	 *
	 * @param FacebookServerSideEvent $server_event The server event to generate the pixel code for.
	 * @param bool                    $script_tag   Whether to wrap the pixel code in a script tag. Default to false.
	 *
	 * @return string The pixel code for the given server event.
	 *
	 * @since 1.0.0
	 */
	public static function generatePixelCode( $server_event, $script_tag = false ) {
		$code = PixelRenderer::render(
			array( $server_event ),
			self::TRACKING_NAME,
			$script_tag
		);
		$code = sprintf(
			'
<!-- Meta Pixel Event Code -->
%s
<!-- End Meta Pixel Event Code -->
      ',
			$code
		);
		return $code;
	}

	/**
	 * Enqueues a Meta Pixel event code for a given server event.
	 *
	 * This method renders the Meta Pixel code for the given server event,
	 * and then enqueues it using the WooCommerce JavaScript enqueueing
	 * system.
	 *
	 * @param FacebookServerSideEvent $server_event The server event to enqueue the pixel code for.
	 *
	 * @return string The Meta Pixel code for the given server event.
	 *
	 * @since 1.0.0
	 */
	public static function enqueuePixelCode( $server_event ) {
		$code = self::generatePixelCode( $server_event, false );
		wc_enqueue_js( $code );
		return $code;
	}
}
