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
	 * Resolved _fbc value for this request. False means not yet resolved.
	 *
	 * @var string|null|false
	 */
	private static $resolved_fbc = false;

	/**
	 * Resolved _fbp value for this request. False means not yet resolved.
	 *
	 * @var string|null|false
	 */
	private static $resolved_fbp = false;

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
			} catch ( \Throwable $exception ) {
				FacebookPluginUtils::log_once_daily(
					'init',
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

			if ( FacebookSignalState::is_held() ) {
				$fbc = self::get_fbc();
				$fbp = self::get_fbp();

				if ( ! empty( $fbc ) ) {
					FacebookSignalState::set_attribution_data( 'fbc', $fbc );
				}
				if ( ! empty( $fbp ) ) {
					FacebookSignalState::set_attribution_data( 'fbp', $fbp );
				}

				foreach ( $cookies_to_set as $cookie ) {
					if ( '_fbp' === $cookie->name ) {
						FacebookSignalState::set_attribution_data( 'fbp_domain', $cookie->domain );
					} elseif ( '_fbc' === $cookie->name ) {
						FacebookSignalState::set_attribution_data( 'fbc_domain', $cookie->domain );
					}
				}

				return;
			}

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
		} catch ( \Throwable $exception ) {
			FacebookPluginUtils::log_once_daily(
				'setup',
				'Meta Pixel: Error setting ParamBuilder cookies: '
				. $exception->getMessage()
			);
		}
	}

	/**
	 * Gets the _fbc value from ParamBuilder.
	 *
	 * @return string|null The _fbc value, or null if unavailable.
	 */
	public static function get_fbc() {
		if ( false !== self::$resolved_fbc ) {
			return self::$resolved_fbc;
		}

		self::$resolved_fbc = null;
		try {
			$param_builder = self::get_instance();
			if ( null !== $param_builder ) {
				$fbc = $param_builder->getFbc();
				if ( ! empty( $fbc ) ) {
					self::$resolved_fbc = $fbc;
				}
			}
		} catch ( \Throwable $exception ) {
			FacebookPluginUtils::log_once_daily(
				'fbc',
				'Meta Pixel: Error getting FBC from ParamBuilder: '
				. $exception->getMessage()
			);
		}
		return self::$resolved_fbc;
	}

	/**
	 * Gets the _fbp value from ParamBuilder.
	 *
	 * @return string|null The _fbp value, or null if unavailable.
	 */
	public static function get_fbp() {
		if ( false !== self::$resolved_fbp ) {
			return self::$resolved_fbp;
		}

		self::$resolved_fbp = null;
		try {
			$param_builder = self::get_instance();
			if ( null !== $param_builder ) {
				$fbp = $param_builder->getFbp();
				if ( ! empty( $fbp ) ) {
					self::$resolved_fbp = $fbp;
				}
			}
		} catch ( \Throwable $exception ) {
			FacebookPluginUtils::log_once_daily(
				'fbp',
				'Meta Pixel: Error getting FBP from ParamBuilder: '
				. $exception->getMessage()
			);
		}
		return self::$resolved_fbp;
	}
}
