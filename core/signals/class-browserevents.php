<?php
/**
 * Facebook Pixel Plugin BrowserEvents class.
 *
 * The single browser-side events class: it owns the Meta Pixel (fbq) — the
 * base/init/noscript bootstrap, the per-event pixel code, and rendering a
 * tracked server Event into browser pixel code. Consolidates the former
 * FacebookPixel + PixelRenderer classes.
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

use ReflectionClass;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\CustomData;

/**
 * Class BrowserEvents
 */
class BrowserEvents {
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

    const EVENT_ID       = 'eventID';
    const TRACK          = 'track';
    const TRACK_CUSTOM   = 'trackCustom';
    const SCRIPT_TAG     = "<script type='text/javascript'>%s</script>";
    const FBQ_EVENT_CODE = "fbq('%s', '%s', %s, %s);";
    const FBQ_AGENT_CODE = "fbq('set', 'agent', '%s', '%s');";

    /**
     * The Facebook Pixel ID.
     *
     * @var string
     */
    private $pixel_id = '';

    /**
     * The Facebook Pixel base code.
     *
     * @var string
     */
    private $pixel_base_code = "
<!-- Meta Pixel Code -->
<script type='text/javascript'>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
</script>
<!-- End Meta Pixel Code -->
";

    /**
     * The Facebook Pixel fbq code without script.
     *
     * @var string
     */
    private $pixel_fbq_code_without_script = "
    fbq('%s', '%s'%s%s);
  ";

    /**
     * The Facebook Pixel noscript code.
     *
     * @var string
     */
    private $pixel_noscript_code = '
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
    public function initialize( $pixel_id = '' ) {
        $this->pixel_id = $pixel_id;
    }

    /**
     * Gets FB pixel ID
     */
    public function get_pixel_id() {
        return $this->pixel_id;
    }

    /**
     * Sets FB pixel ID
     *
     * @param string $pixel_id The Facebook Pixel ID to be set.
     */
    public function set_pixel_id( $pixel_id ) {
        $this->pixel_id = $pixel_id;
    }

    /**
     * Gets FB pixel base code
     */
    public function get_pixel_base_code() {
        return $this->pixel_base_code;
    }

    /**
     * Gets OpenBridge set config code
     */
    public function get_open_bridge_config_code() {
      if ( empty( $this->pixel_id ) ) {
          return;
      }

        $code = "var url = window.location.origin + '?ob=open-bridge';
            fbq('set', 'openbridge', '%s', url);";
        return sprintf( $code, $this->pixel_id );
    }

    /**
     * Renders tracked server events into browser pixel code.
     *
     * @param array  $events                  The events to render.
     * @param string $fb_integration_tracking The integration tracking name.
     * @param bool   $script_tag              Whether to wrap in a script tag.
     * @return string
     */
    public function render(
        $events,
        $fb_integration_tracking,
        $script_tag = true
    ) {
        if ( empty( $events ) ) {
            return '';
        }
        $code = sprintf(
            self::FBQ_AGENT_CODE,
            FacebookWordpressOptions::get_agent_string(),
            FacebookWordpressOptions::get_active_pixel_id()
        );
        foreach ( $events as $event ) {
            $code .= $this->get_event_track_code( $event, $fb_integration_tracking );
        }
        return $script_tag ? sprintf( self::SCRIPT_TAG, $code ) : $code;
    }

    /**
     * Generates the browser pixel code for a single tracked server Event.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @param bool|string                                              $fb_integration_tracking Tracking label.
     * @return string
     */
    private function get_event_track_code(
        $event,
        $fb_integration_tracking
    ) {
        if ( FacebookSignalState::is_held() ) {
            return $this->get_event_queue_code(
                $event,
                $fb_integration_tracking
            );
        }

        $event_data[ self::EVENT_ID ] = $event->getEventId();

        $custom_data = $event->getCustomData() !== null ?
        $event->getCustomData() : new CustomData();

        $normalized_custom_data = $custom_data->normalize();
        if ( ! is_null( $fb_integration_tracking ) ) {
            $normalized_custom_data[ self::FB_INTEGRATION_TRACKING_KEY ] =
            $fb_integration_tracking;
        }

        $class      = new ReflectionClass( __CLASS__ );
        $const_name = strtoupper( (string) $event->getEventName() );
        return sprintf(
            self::FBQ_EVENT_CODE,
            $class->hasConstant( $const_name ) ?
            self::TRACK : self::TRACK_CUSTOM,
            $event->getEventName(),
            wp_json_encode( $normalized_custom_data, JSON_PRETTY_PRINT ),
            wp_json_encode( $event_data, JSON_PRETTY_PRINT )
        );
    }

    /**
     * Generates queueing code for a held server Event.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @param bool|string                                              $fb_integration_tracking Tracking label.
     * @return string
     */
    private function get_event_queue_code(
        $event,
        $fb_integration_tracking
    ) {
        $custom_data            = $event->getCustomData() !== null ?
            $event->getCustomData() : new CustomData();
        $normalized_custom_data = $custom_data->normalize();

        if ( ! is_null( $fb_integration_tracking ) ) {
            $normalized_custom_data[ self::FB_INTEGRATION_TRACKING_KEY ] =
                $fb_integration_tracking;
        }

        if ( ! empty( $event->getEventId() ) ) {
            $normalized_custom_data[ self::EVENT_ID ] = $event->getEventId();
        }

        $payload               = $this->build_queue_payload(
            $event->getEventName(),
            $normalized_custom_data,
            '',
            null
        );
        $payload['event_time'] = $event->getEventTime();

        return 'FacebookSignal.queueEvent(' .
            wp_json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP |
                    JSON_HEX_APOS | JSON_HEX_QUOT
            ) .
            ');';
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
    public function get_pixel_track_code(
        $event,
        $param = array(),
        $tracking_name = '',
        $with_script_tag = true
    ) {
        if ( empty( $this->pixel_id ) ) {
            return;
        }

        if ( $this->are_signals_held() ) {
            return $this->get_pixel_queue_code(
                $event,
                $param,
                $tracking_name,
                $with_script_tag
            );
        }

        $code      = $with_script_tag ? "<script type='text/javascript'>" .
        $this->pixel_fbq_code_without_script .
        '</script>' : $this->pixel_fbq_code_without_script;
        $param_str = $param;
        if ( is_array( $param ) ) {
            if ( ! empty( $tracking_name ) ) {
                $param[ self::FB_INTEGRATION_TRACKING_KEY ] = $tracking_name;
            }
            $param_str = wp_json_encode( $param, JSON_PRETTY_PRINT );
        }
        $class      = new ReflectionClass( __CLASS__ );
        $const_name = strtoupper( (string) $event );
        return sprintf(
            $code,
            $class->hasConstant( $const_name ) ? 'track' : 'trackCustom',
            $event,
            ', ' . $param_str,
            ''
        );
    }

    /**
     * Gets queueing code for a held event.
     *
     * @param string $event           Event name.
     * @param array  $param           Event parameters.
     * @param string $tracking_name   Integration tracking name.
     * @param bool   $with_script_tag Whether to include a script wrapper.
     *
     * @return string
     */
    private function get_pixel_queue_code(
        $event,
        $param,
        $tracking_name,
        $with_script_tag
    ) {
        $is_custom = ( new ReflectionClass( __CLASS__ ) )
            ->getConstant( strtoupper( $event ) ) === false;

        if ( is_array( $param ) ) {
            $payload = $this->build_queue_payload(
                $event,
                $param,
                $tracking_name,
                $is_custom
            );
            $code    = 'FacebookSignal.queueEvent(' .
                wp_json_encode(
                    $payload,
                    JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP |
                        JSON_HEX_APOS | JSON_HEX_QUOT
                ) .
                ');';
        } else {
            $code = sprintf(
                'FacebookSignal.queueEvent({"event_name":%s,'
                . '"custom_data":%s,"event_id":null,"event_time":%d,'
                . '"is_custom":%s});',
                wp_json_encode(
                    $event,
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                ),
                (string) $param,
                time(),
                $is_custom ? 'true' : 'false'
            );
        }

        if ( ! $with_script_tag ) {
            return $code;
        }

        return "<script type='text/javascript'>{$code}</script>";
    }

    /**
     * Build queue payload for a held event.
     *
     * @param string       $event         Event name.
     * @param array|string $param         Event params.
     * @param string       $tracking_name Integration tracking name.
     * @param bool|null    $is_custom     Whether the event is a custom event;
     *                                    null = auto-detect.
     *
     * @return array
     */
    public function build_queue_payload(
        $event,
        $param = array(),
        $tracking_name = '',
        $is_custom = null
    ) {
        $custom_data = is_array( $param ) ? $param : array();
        $event_id    = null;

        if ( isset( $custom_data['eventID'] ) ) {
            $event_id = $custom_data['eventID'];
            unset( $custom_data['eventID'] );
        }

        if ( ! empty( $tracking_name ) ) {
            $custom_data[ self::FB_INTEGRATION_TRACKING_KEY ] = $tracking_name;
        }

        if ( null === $is_custom ) {
            $is_custom = ( new ReflectionClass( __CLASS__ ) )
                ->getConstant( strtoupper( $event ) ) === false;
        }

        $payload = array(
            'event_name'  => $event,
            'custom_data' => $custom_data,
            'event_id'    => $event_id,
            'event_time'  => time(),
            'is_custom'   => (bool) $is_custom,
        );

        if ( ! empty( $event_id ) ) {
            $queued_user_data = FacebookSignalState::get_queued_user_data( $event_id );
            if ( null !== $queued_user_data ) {
                $payload['user_data'] = $queued_user_data;
            }
        }

        return $payload;
    }

    /**
     * Whether signals are held for the current request.
     *
     * @return bool
     */
    private function are_signals_held() {
        return class_exists( '\FacebookPixelPlugin\Core\FacebookSignalState' ) &&
            FacebookSignalState::is_held();
    }

    /**
     * Gets FB pixel noscript code
     *
     * @param string $event The name of the pixel event.
     * @param array  $cd The parameters for the pixel event.
     * @param string $tracking_name The tracking name for the pixel event.
     */
    public function get_pixel_noscript_code(
        $event = 'PageView',
        $cd = array(),
        $tracking_name = ''
    ) {
        if ( empty( $this->pixel_id ) ) {
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
            $this->pixel_noscript_code,
            $this->pixel_id,
            $event,
            $data
        );
    }

    /**
     * Gets FB pixel AddToCart code
     *
     * @param array  $param The parameters for the pixel event.
     * @param string $tracking_name The tracking name for the pixel event.
     * @param bool   $with_script_tag Whether to include the pixel track code.
     */
    public function get_pixel_add_to_cart_code(
        $param = array(),
        $tracking_name = '',
        $with_script_tag = true
    ) {
        return $this->get_pixel_track_code(
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
     * @param bool   $with_script_tag Whether to include the pixel track code.
     */
    public function get_pixel_initiate_checkout_code(
        $param = array(),
        $tracking_name = '',
        $with_script_tag = true
    ) {
        return $this->get_pixel_track_code(
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
     * @param bool   $with_script_tag Whether to include the pixel track code.
     */
    public function get_pixel_lead_code(
        $param = array(),
        $tracking_name = '',
        $with_script_tag = true
    ) {
        return $this->get_pixel_track_code(
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
     * @param bool   $with_script_tag Whether to include the pixel track code.
     */
    public function get_pixel_page_view_code(
        $param = array(),
        $tracking_name = '',
        $with_script_tag = true
    ) {
        return $this->get_pixel_track_code(
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
     * @param bool   $with_script_tag Whether to include the pixel track code.
     */
    public function get_pixel_purchase_code(
        $param = array(),
        $tracking_name = '',
        $with_script_tag = true
    ) {
        return $this->get_pixel_track_code(
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
     * @param bool   $with_script_tag Whether to include the pixel track code.
     */
    public function get_pixel_view_content_code(
        $param = array(),
        $tracking_name = '',
        $with_script_tag = true
    ) {
        return $this->get_pixel_track_code(
            self::VIEWCONTENT,
            $param,
            $tracking_name,
            $with_script_tag
        );
    }
}
