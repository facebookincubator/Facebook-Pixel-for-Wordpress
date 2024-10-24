<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase WordPress.Files.FileName.InvalidClassFileName
/**
 * Facebook Pixel Plugin EDDUtils class.
 *
 * This file contains the main logic for EDDUtils.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define EDDUtils class.
 *
 * @return void
 */

namespace FacebookPixelPlugin\Integration;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * EDDUtils class.
 */
class EDDUtils {
	/**
	 * Return the currency code.
	 *
	 * @since 3.0.0
	 * @return string The currency code.
	 */
	public static function get_currency() {
		return edd_get_currency();
	}

	/**
	 * Get the total of the cart.
	 *
	 * @since 3.0.0
	 *
	 * @return float The total amount of the cart.
	 */
	public static function get_cart_total() {
		return EDD()->cart->get_total();
	}
}
