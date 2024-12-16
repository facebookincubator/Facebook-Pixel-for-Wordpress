<?php
/**
 * Facebook Pixel Plugin PixelRenderer class.
 *
 * This file contains the main logic for PixelRenderer.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define PixelRenderer class.
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

use ReflectionClass;
use FacebookAds\Object\ServerSide\CustomData;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class PixelRenderer
 */
class PixelRenderer {
    const EVENT_ID                = 'eventID';
    const TRACK                   = 'track';
    const TRACK_CUSTOM            = 'trackCustom';
    const FB_INTEGRATION_TRACKING = 'fb_integration_tracking';
    const SCRIPT_TAG              =
    "<script type='text/javascript'>%s</script>";
    const FBQ_EVENT_CODE          = "fbq('%s', '%s', %s, %s);";
    const FBQ_AGENT_CODE          = "fbq('set', 'agent', '%s', '%s');";

    /**
     * Render the pixel events
     *
     * @param array $events The array of events, each event is an
     * array with the following keys:
     *                      - event_name: the name of the event
     *                      - event_id: the id of the event (optional)
     *                      - custom_data: the custom data
     * for the event (optional).
     * @param bool  $fb_integration_tracking Whether to track the
     * event as a Facebook integration.
     * @param bool  $script_tag Whether to wrap the
     * generated code with a script tag.
     *
     * @return string The rendered pixel events
     */
    public static function render(
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
            FacebookWordpressOptions::get_pixel_id()
        );
        foreach ( $events as $event ) {
            $code .= self::get_pixel_track_code( $event, $fb_integration_tracking );
        }
        return $script_tag ? sprintf( self::SCRIPT_TAG, $code ) : $code;
    }


    /**
     * Generate the Facebook Pixel track code for an event
     *
     * @param \FacebookPixelPlugin\Core\Event $event The event to
     * generate the track code for.
     * @param bool                            $fb_integration_tracking Whether
     * to track the event as a Facebook integration.
     *
     * @return string The generated track code
     */
    private static function get_pixel_track_code(
        $event,
        $fb_integration_tracking
    ) {
        $event_data[ self::EVENT_ID ] = $event->getEventId();

        $custom_data = $event->getCustomData() !== null ?
        $event->getCustomData() : new CustomData();

        $normalized_custom_data = $custom_data->normalize();
        if ( ! is_null( $fb_integration_tracking ) ) {
            $normalized_custom_data[ self::FB_INTEGRATION_TRACKING ] =
            $fb_integration_tracking;
        }

        $class = new ReflectionClass(
            'FacebookPixelPlugin\Core\FacebookPixel'
        );
        return sprintf(
            self::FBQ_EVENT_CODE,
            $class->getConstant( strtoupper( $event->getEventName() ) ) !== false ?
            self::TRACK : self::TRACK_CUSTOM,
            $event->getEventName(),
            wp_json_encode( $normalized_custom_data, JSON_PRETTY_PRINT ),
            wp_json_encode( $event_data, JSON_PRETTY_PRINT )
        );
    }
}
