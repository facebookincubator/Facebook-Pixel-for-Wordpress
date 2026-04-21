<?php
/**
 * Facebook Pixel Plugin FacebookParamBuilder class.
 *
 * This file contains the ParamBuilder integration logic for
 * the CAPI Parameter Builder SDK.
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

namespace FacebookPixelPlugin\Core;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class FacebookParamBuilder
 *
 * Manages the CAPI Parameter Builder SDK integration.
 * Provides server-side cookie management and client-side JS enqueue
 * to improve _fbc and _fbp coverage for Conversions API events.
 */
class FacebookParamBuilder {

	/**
	 * URL for the client-side CAPI param builder script.
	 *
	 * @var string
	 */
	const CLIENT_JS_URL = 'https://unpkg.com/meta-capi-param-builder-clientjs/dist/clientParamBuilder.bundle.js';

	/**
	 * Script handle for the client-side param builder.
	 *
	 * @var string
	 */
	const CLIENT_JS_HANDLE = 'meta-capi-param-builder';

	/**
	 * Shared ParamBuilder instance.
	 *
	 * @var \FacebookAds\ParamBuilder|null
	 */
	private static $instance = null;

	/**
	 * Whether the server-side setup has been completed.
	 *
	 * @var bool
	 */
	private static $server_setup_done = false;

	/**
	 * Gets or creates the ParamBuilder singleton instance.
	 *
	 * Initializes the ParamBuilder with the site URL and processes
	 * the current request to extract _fbc/_fbp parameters.
	 *
	 * @return \FacebookAds\ParamBuilder|null The ParamBuilder instance,
	 *                                        or null on error.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			try {
				$site_url       = get_site_url();
				self::$instance = new \FacebookAds\ParamBuilder(
					array( $site_url )
				);
				self::$instance->processRequest(
					$site_url,
					$_GET, // phpcs:ignore WordPress.Security.NonceVerification
					$_COOKIE,
					isset( $_SERVER['HTTP_REFERER'] )
						? sanitize_text_field(
							wp_unslash( $_SERVER['HTTP_REFERER'] )
						)
						: null
				);
			} catch ( \Exception $exception ) {
				error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					'Meta Pixel: Error initializing CAPI Parameter Builder: '
					. $exception->getMessage()
				);
				self::$instance = null;
			}
		}

		return self::$instance;
	}

	/**
	 * Server-side setup: sets cookies from ParamBuilder.
	 *
	 * Must be called early in the request lifecycle before
	 * headers are sent.
	 */
	public static function server_setup() {
		if ( self::$server_setup_done ) {
			return;
		}
		self::$server_setup_done = true;

		try {
			$param_builder = self::get_instance();
			if ( null === $param_builder ) {
				return;
			}

			$cookies_to_set = $param_builder->getCookiesToSet();

			if ( ! headers_sent() ) {
				foreach ( $cookies_to_set as $cookie ) {
					setcookie(
						$cookie->name,
						$cookie->value,
						time() + $cookie->max_age,
						'/',
						$cookie->domain
					);
				}
			}
		} catch ( \Exception $exception ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				'Meta Pixel: Error setting ParamBuilder cookies: '
				. $exception->getMessage()
			);
		}
	}

	/**
	 * Returns the client-side ParamBuilder script tag for inline injection.
	 *
	 * This script loads BEFORE fbevents.js so that ParamBuilder takes
	 * priority for _fbc/_fbp cookie management on the client side.
	 *
	 * @return string The script HTML, or empty string if pixel is not configured.
	 */
	public static function get_client_script_tag() {
		$pixel_id = FacebookWordpressOptions::get_pixel_id();
		if ( ! FacebookPluginUtils::is_positive_integer( $pixel_id ) ) {
			return '';
		}

		$url = esc_url( self::CLIENT_JS_URL );
		return "<!-- Meta CAPI Param Builder -->\n"
			. "<script type='text/javascript' src='{$url}'></script>\n"
			. "<script type='text/javascript'>\n"
			. "if (typeof clientParamBuilder !== 'undefined') {\n"
			. "  clientParamBuilder.processAndCollectAllParams(window.location.href);\n"
			. "}\n"
			. "</script>\n";
	}

	/**
	 * Gets the _fbc value from ParamBuilder.
	 *
	 * @return string|null The _fbc value, or null if unavailable.
	 */
	public static function get_fbc() {
		try {
			$param_builder = self::get_instance();
			if ( null !== $param_builder ) {
				$fbc = $param_builder->getFbc();
				if ( ! empty( $fbc ) ) {
					return $fbc;
				}
			}
		} catch ( \Exception $exception ) {
			// Silently fail — fallback to other methods.
		}
		return null;
	}

	/**
	 * Gets the _fbp value from ParamBuilder.
	 *
	 * @return string|null The _fbp value, or null if unavailable.
	 */
	public static function get_fbp() {
		try {
			$param_builder = self::get_instance();
			if ( null !== $param_builder ) {
				$fbp = $param_builder->getFbp();
				if ( ! empty( $fbp ) ) {
					return $fbp;
				}
			}
		} catch ( \Exception $exception ) {
			// Silently fail — fallback to other methods.
		}
		return null;
	}
}
