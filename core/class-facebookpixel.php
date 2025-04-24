<?php
/**
 * Facebook Pixel Plugin FacebookPixel class.
 *
 * This file contains the main logic for FacebookPixel.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookPixel class.
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

namespace FacebookPixelPlugin\Core;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

use ReflectionClass;

/**
 * Class FacebookPixel
 */
class FacebookPixel {
    const ADDPAYMENTINFO       = 'AddPaymentInfo';
    const ADDTOCART            = 'AddToCart';
    const ADDTOWISHLIST        = 'AddToWishlist';
    const COMPLETEREGISTRATION = 'CompleteRegistration';
    const CONTACT              = 'Contact';
    const CUSTOMIZEPRODUCT     = 'CustomizeProduct';
    const DONATE               = 'Donate';
    const FINDLOCATION         = 'FindLocation';
    const INITIATECHECKOUT     = 'InitiateCheckout';
    const LEAD                 = 'Lead';
    const PAGEVIEW             = 'PageView';
    const PURCHASE             = 'Purchase';
    const SCHEDULE             = 'Schedule';
    const SEARCH               = 'Search';
    const STARTTRIAL           = 'StartTrial';
    const SUBMITAPPLICATION    = 'SubmitApplication';
    const SUBSCRIBE            = 'Subscribe';
    const VIEWCONTENT          = 'ViewContent';

    const FB_INTEGRATION_TRACKING_KEY = 'fb_integration_tracking';

    /**
     * The Facebook Pixel ID.
     *
     * @var string
     */
    private static $pixel_id = '';

    /**
     * The Facebook Pixel base code.
     *
     * @var string
     */
    private static $pixel_base_code = "
<!-- Meta Pixel Code -->
<script type='text/javascript'>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js?v=next');
</script>
<!-- End Meta Pixel Code -->
";

    /**
     * The Facebook Pixel fbq code without script.
     *
     * @var string
     */
    private static $pixel_fbq_code_without_script = "
    fbq('%s', '%s'%s%s);
  ";

    /**
     * The Facebook Pixel noscript code.
     *
     * @var string
     */
    private static $pixel_noscript_code = '
<!-- Meta Pixel Code -->
<noscript>
<img height="1" width="1" style="display:none" alt="fbpx"
src="https://www.facebook.com/tr?id=%s&ev=%s%s&noscript=1" />
</noscript>
<!-- End Meta Pixel Code -->
';

    /**
     * Initializes the Facebook Pixel with the given pixel ID.
     *
     * @param string $pixel_id The Facebook Pixel ID to be set.
     * Defaults to an empty string.
     */
    public static function initialize( $pixel_id = '' ) {
        self::$pixel_id = $pixel_id;
    }

    /**
     * Gets FB pixel ID
     */
    public static function get_pixel_id() {
        return self::$pixel_id;
    }

    /**
     * Sets FB pixel ID
     *
     * @param string $pixel_id The Facebook Pixel ID to be set.
     */
    public static function set_pixel_id( $pixel_id ) {
        self::$pixel_id = $pixel_id;
    }

    /**
     * Gets FB pixel base code
     */
    public static function get_pixel_base_code() {
        return self::$pixel_base_code;
    }

    /**
     * Gets OpenBridge set config code
     */
    public static function get_open_bridge_config_code() {
      if ( empty( self::$pixel_id ) ) {
          return;
      }

        $code = "var url = window.location.origin + '?ob=open-bridge';
            fbq('set', 'openbridge', '%s', url);";
        return sprintf( $code, self::$pixel_id );
    }


    /**
     * Gets FB pixel init code
     *
     * @param string $agent_string The agent string to be used
     * in the pixel init code.
     * @param array  $param        The parameters for the pixel event.
     * Defaults to an empty array.
     * @param bool   $include_capi        Whether CAPI injection is
     * enabled or not.
     * @param bool   $with_script_tag Whether to include the script tag in
     * the pixel init code. Defaults to true.
     */
    public static function get_pixel_init_code(
        $agent_string,
        $param,
        $include_capi,
        $with_script_tag = true
    ) {
        if ( empty( self::$pixel_id ) ) {
            return;
        }

        $capi_integration_injection    = $include_capi ?
            ( self::get_open_bridge_config_code() . PHP_EOL ) : '';
        $pixel_fbq_code_without_script = $capi_integration_injection .
            "fbq('%s', '%s'%s%s)";

        $code      = $with_script_tag ? "<script type='text/javascript'>" .
        $pixel_fbq_code_without_script .
        '</script>' : $pixel_fbq_code_without_script;
        $param_str = $param;
        if ( is_array( $param ) ) {
            $param_str = wp_json_encode(
                $param,
                JSON_PRETTY_PRINT | JSON_FORCE_OBJECT
            );
        }
        $agent_param = array( 'agent' => $agent_string );
        return sprintf(
            $code,
            'init',
            self::$pixel_id,
            ', ' . $param_str,
            ', ' . wp_json_encode( $agent_param, JSON_PRETTY_PRINT )
        );
    }

    /**
     * Gets FB pixel track code
     * $param is the parameter for the pixel event.
     *   If it is an array, FB_INTEGRATION_TRACKING_KEY parameter with
     * $tracking_name value will automatically
     *   be added into the $param. If it is a string, please append the
     * FB_INTEGRATION_TRACKING_KEY parameter
     *   with its tracking name into the JS Parameter block
     *
     * @param string $event The name of the pixel event.
     * @param array  $param The parameters for the pixel event.
     * @param string $tracking_name The tracking name for the pixel event.
     * @param bool   $with_script_tag Whether to include the script tag in
     * the pixel track code.
     * @return string The pixel track code.
     */
    public static function get_pixel_track_code(
        $event,
        $param = array(),
        $tracking_name = '',
        $with_script_tag = true
    ) {
        if ( empty( self::$pixel_id ) ) {
            return;
        }

        $code      = $with_script_tag ? "<script type='text/javascript'>" .
        self::$pixel_fbq_code_without_script .
        '</script>' : self::$pixel_fbq_code_without_script;
        $param_str = $param;
        if ( is_array( $param ) ) {
            if ( ! empty( $tracking_name ) ) {
                $param[ self::FB_INTEGRATION_TRACKING_KEY ] = $tracking_name;
            }
            $param_str = wp_json_encode( $param, JSON_PRETTY_PRINT );
        }
        $class = new ReflectionClass( __CLASS__ );
        return sprintf(
            $code,
            $class->getConstant(
                strtoupper( $event )
            ) !== false ? 'track' : 'trackCustom',
            $event,
            ', ' . $param_str,
            ''
        );
    }

    /**
     * Gets FB pixel noscript code
     *
     * @param string $event The name of the pixel event.
     * @param array  $cd The parameters for the pixel event.
     * @param string $tracking_name The tracking name for the pixel event.
     */
    public static function get_pixel_noscript_code(
        $event = 'PageView',
        $cd = array(),
        $tracking_name = ''
    ) {
        if ( empty( self::$pixel_id ) ) {
            return;
        }

            $data = '';
        foreach ( $cd as $k => $v ) {
            $data .= '&cd[' . $k . ']=' . $v;
        }
        if ( ! empty( $tracking_name ) ) {
            $data .= '&cd[' . self::FB_INTEGRATION_TRACKING_KEY . ']=' .
            $tracking_name;
        }
        return sprintf(
            self::$pixel_noscript_code,
            self::$pixel_id,
            $event,
            $data
        );
    }

    /**
     * Gets FB pixel AddToCart code
     *
     * @param array  $param The parameters for the pixel event.
     * @param string $tracking_name The tracking name for the pixel event.
     * @param bool   $with_script_tag Whether to include the script
     * tag in the pixel track code.
     */
    public static function get_pixel_add_to_cart_code(
        $param = array(),
        $tracking_name = '',
        $with_script_tag = true
    ) {
        return self::get_pixel_track_code(
            self::ADDTOCART,
            $param,
            $tracking_name,
            $with_script_tag
        );
    }

    /**
     * Gets FB pixel InitiateCheckout code
     *
     * @param array  $param The parameters for the pixel event.
     * @param string $tracking_name The tracking name for the pixel event.
     * @param bool   $with_script_tag Whether to include the
     * script tag in the pixel track code.
     */
    public static function get_pixel_initiate_checkout_code(
        $param = array(),
        $tracking_name = '',
        $with_script_tag = true
    ) {
        return self::get_pixel_track_code(
            self::INITIATECHECKOUT,
            $param,
            $tracking_name,
            $with_script_tag
        );
    }

    /**
     * Gets FB pixel Lead code
     *
     * @param array  $param The parameters for the pixel event.
     * @param string $tracking_name The tracking name for the pixel event.
     * @param bool   $with_script_tag Whether to include the
     * script tag in the pixel track code.
     */
    public static function get_pixel_lead_code(
        $param = array(),
        $tracking_name = '',
        $with_script_tag = true
    ) {
        return self::get_pixel_track_code(
            self::LEAD,
            $param,
            $tracking_name,
            $with_script_tag
        );
    }

    /**
     * Gets FB pixel PageView code
     *
     * @param array  $param The parameters for the pixel event.
     * @param string $tracking_name The tracking name for the pixel event.
     * @param bool   $with_script_tag Whether to include the script
     * tag in the pixel track code.
     */
    public static function get_pixel_page_view_code(
        $param = array(),
        $tracking_name = '',
        $with_script_tag = true
    ) {
        return self::get_pixel_track_code(
            self::PAGEVIEW,
            $param,
            $tracking_name,
            $with_script_tag
        );
    }

    /**
     * Gets FB pixel Purchase code
     *
     * @param array  $param The parameters for the pixel event.
     * @param string $tracking_name The tracking name for the pixel event.
     * @param bool   $with_script_tag Whether to include the script
     *  tag in the pixel track code.
     */
    public static function get_pixel_purchase_code(
        $param = array(),
        $tracking_name = '',
        $with_script_tag = true
    ) {
        return self::get_pixel_track_code(
            self::PURCHASE,
            $param,
            $tracking_name,
            $with_script_tag
        );
    }

    /**
     * Gets FB pixel ViewContent code
     *
     * @param array  $param The parameters for the pixel event.
     * @param string $tracking_name The tracking name for the pixel event.
     * @param bool   $with_script_tag Whether to include the script tag in
     * the pixel track code.
     */
    public static function get_pixel_view_content_code(
        $param = array(),
        $tracking_name = '',
        $with_script_tag = true
    ) {
        return self::get_pixel_track_code(
            self::VIEWCONTENT,
            $param,
            $tracking_name,
            $with_script_tag
        );
    }
}
