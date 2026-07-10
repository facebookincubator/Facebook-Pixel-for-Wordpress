<?php
/**
 * Facebook Pixel Plugin FacebookSignalsBase class.
 *
 * This file contains the base logic shared by the signals subsystem.
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

use Exception;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\CustomData;

/**
 * Base class for the signals subsystem.
 *
 * Integrations depend on a FacebookSignalsBase instance (injected via
 * TrackableIntegrationBase) to interact with signal hold/release/queue
 * behavior. Concrete behavior will be layered on in later steps.
 */
abstract class FacebookSignalsBase {

    const FB_INTEGRATION_TRACKING_KEY = FacebookPixel::FB_INTEGRATION_TRACKING_KEY;

    /**
     * The partner agent string sent with events.
     *
     * @var string
     */
    public $agent_string;

    /**
     * The active Meta Pixel id.
     *
     * @var string
     */
    public $pixel_id;

    /**
     * Whether signal sending is currently held (queued) pending release.
     *
     * @var bool
     */
    public $is_held;

    /**
     * The Conversions API (server-side) transport.
     *
     * @var Capi
     */
    public Capi $capi;

    /**
     * The browser Pixel transport.
     *
     * @var Pixel
     */
    public Pixel $pixel;

    /**
     * Builds a server event from normalized event data.
     *
     * Routes through ServerEventFactory::safe_create_event so the data is split
     * into user_data / custom_data, user data is AAM-normalized, and the
     * fb_integration_tracking custom property is added at creation time. Because
     * the property lives on the event's custom_data from the start, both the CAPI
     * and Pixel transports pick it up without needing the integration name.
     *
     * @param string      $event_name  The Meta event name (e.g. 'Lead').
     * @param array       $data        Normalized event data (user_data + custom_data keys).
     * @param string|null $integration The integration name for fb_integration_tracking.
     * @param bool        $prefer_referrer_for_event_src Whether to use the referrer
     *                    URL (instead of the current request URL) as the event
     *                    source URL. Default true, which suits AJAX/form submits
     *                    (fired on admin-ajax/REST, where the referrer is the real
     *                    page). Pass false for non-AJAX page-render events (e.g.
     *                    WooCommerce/EDD ViewContent/InitiateCheckout/Purchase),
     *                    where the current URL is the actual page.
     * @return \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event
     */
    public function generate_event( $event_name, array $data = array(), $integration = null, $prefer_referrer_for_event_src = true ) {
        return ServerEventFactory::safe_create_event(
            $event_name,
            function () use ( $data ) {
                return $data;
            },
            array(),
            $integration,
            $prefer_referrer_for_event_src
        );
    }

    /**
     * Whether the signals subsystem is enabled.
     *
     * @return bool True when signals tracking is active.
     */
    public function is_enabled() {
        return true;
    }

    /**
     * Sets up the default signal behavior for the integration.
     *
     * Intended to create the frontend and backend PageView events, configure the
     * ParamBuilder, and wire up the hold/release behavior. Not yet implemented.
     *
     * @throws \Exception Always, until this is implemented.
     * @return void
     */
    public function defaults() {
        // Create the PageView events for frontend & backend.
        // ParamBuilder setup.
        // Hold/Release setup.
        throw new \Exception( 'Not implemented' );
    }
}
