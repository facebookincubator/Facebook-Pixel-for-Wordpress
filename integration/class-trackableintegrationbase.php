<?php
/**
 * Facebook Pixel Plugin TrackableIntegrationBase class.
 *
 * This file contains the base logic for trackable plugin integrations.
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

namespace FacebookPixelPlugin\Integration;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

use FacebookPixelPlugin\Core\FacebookTrackingFacade;
use FacebookPixelPlugin\Core\Capi;
use FacebookPixelPlugin\Utils\ThirdPartyIntegrationsUtil;

/**
 * Base class for plugin integrations that track events through the
 * signals subsystem.
 */
abstract class TrackableIntegrationBase {

    /**
     * Browser delivery mode: enqueue the browser event for the footer flush.
     * Use for non-AJAX page renders.
     *
     * @var string
     */
    const BROWSER_INLINE = 'inline';

    /**
     * Browser delivery mode: hand the browser event to the configured AJAX
     * channel (injected into the host plugin's AJAX response / cart fragments).
     * Use for AJAX submits.
     *
     * @var string
     */
    const BROWSER_AJAX = 'channel';

    /**
     * Browser delivery mode: no browser event (a client-side script fires the
     * Pixel itself).
     *
     * @var string
     */
    const BROWSER_NONE = 'none';

    /**
     * Server delivery mode: send the event to CAPI synchronously in-request.
     *
     * @var string
     */
    const SERVER_SYNC = 'server_sync';

    /**
     * Server delivery mode: send the event to CAPI asynchronously. Not yet
     * implemented.
     *
     * @var string
     */
    const SERVER_ASYNC = 'server_async';

    /**
     * Server delivery mode: do not send the event to CAPI.
     *
     * @var string
     */
    const SERVER_NONE = 'server_none';

    const AJAX_PIXEL_CONTAINER = '';

    /**
     * The tracking facade this integration dispatches events through.
     *
     * @var FacebookTrackingFacade
     */
    protected $signals;

    const PLUGIN_FILE      = '';
    const INTEGRATION_NAME = '';

    /**
     * Constructor.
     *
     * @param FacebookTrackingFacade $signals The tracking facade instance.
     */
    public function __construct( FacebookTrackingFacade $signals ) {
        $this->signals = $signals;
    }

    /**
     * Builds a server event from normalized data, attributed to this
     * integration.
     *
     * @param string $event_name      The Meta event name (e.g. 'Lead').
     * @param array  $data            Normalized event data.
     * @param bool   $prefer_referrer Whether to use the referrer URL as the
     *                                event source URL. Default true (suits
     *                                AJAX/form submits); pass false for
     *                                non-AJAX page-render events.
     * @return \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event
     */
    protected function generate_event( $event_name, array $data = array(), $prefer_referrer = true ) {
        return $this->signals->generate_event( $event_name, $data, static::INTEGRATION_NAME, $prefer_referrer );
    }

    /**
     * Delivers the browser-side event per the given browser mode, then sends the
     * event to CAPI per the given server mode. No runtime routing here: the
     * caller picks the mode (e.g. a dual-mode integration passes BROWSER_AJAX
     * when wp_doing_ajax(), else BROWSER_INLINE), so the decision stays visible
     * at the call site.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event   The event.
     * @param string                                                   $browser BROWSER_INLINE, BROWSER_AJAX or BROWSER_NONE.
     * @param string                                                   $server  SERVER_SYNC, SERVER_ASYNC, SERVER_NONE.
     * @param array                                                    $args    Extra arguments forwarded to deliver_ajax_browser_event() for BROWSER_AJAX.
     * @throws \Exception When the browser delivery mode is not supported.
     * @return void
     */
    protected function deliver( $event, $browser = self::BROWSER_INLINE, $server = self::SERVER_SYNC, $args = array() ) {
        switch ( $browser ) {
            case self::BROWSER_AJAX:
                $this->deliver_ajax_browser_event( $args );
                break;
            case self::BROWSER_INLINE:
                $this->signals->track_inline_browser_event( $event );
                break;
            case self::BROWSER_NONE:
                break;
            default:
                throw new \Exception( esc_html( $browser ) . ' is not implemented' );
        }

        $this->signals->track_server_event( $event, $server );
    }

    /**
     * Delivers the browser-side event for an AJAX request. Base placeholder;
     * integrations that support AJAX browser delivery must override this.
     *
     * @param array $args Extra arguments forwarded from deliver() (e.g. the event).
     * @throws \Exception Always, until overridden.
     * @return void
     */
    protected function deliver_ajax_browser_event( $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Base placeholder; overridden by integrations.
        throw new \Exception( 'Not Implemented' );
    }

    /**
     * Registers the AJAX footer listener: wraps the given listener JavaScript in
     * a <script> element and hands it to the Pixel to print on wp_footer.
     *
     * @param string $listener_js The listener JavaScript to plant in the footer.
     * @return void
     */
    protected function register_ajax_container( $listener_js ) {
        $this->signals->register_ajax_dom_container(
            sprintf(
                "<!-- Meta Pixel Event Code -->
                <script type='text/javascript'>
                %s
                </script>
                <!-- End Meta Pixel Event Code -->",
                $listener_js
            )
        );
    }

    /**
     * Registers raw markup (e.g. an empty container div for an AJAX-driven DOM
     * swap) to be printed in the footer. Unlike register_ajax_container(), the
     * markup is emitted verbatim, not wrapped in a <script> element.
     *
     * @param string $markup The markup to print on wp_footer.
     * @return void
     */
    protected function register_ajax_dom_element( $markup ) {
        $this->signals->register_ajax_dom_container( $markup );
    }

    /**
     * Generates the browser Pixel script for an event, for embedding in an AJAX
     * response so the footer listener can eval it.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event              The event.
     * @param bool                                                     $include_script_tag Whether to wrap the code in a <script> element.
     * @return string The generated Pixel script.
     */
    protected function generate_pixel_script_for_ajax( $event, $include_script_tag ) {
        return $this->signals->generate_pixel_code( $event, $include_script_tag );
    }

    /**
     * Initializes the integration, setting up tracking when the target plugin
     * is available.
     *
     * @return void
     */
    public function initialize() {
        if ( $this->is_integration_available() ) {
            $this->set_up_tracking();
        }
    }

    /**
     * Sets up the plugin hooks that capture and track events.
     *
     * @return void
     */
    abstract protected function set_up_tracking();

    /**
     * Whether the third-party plugin this integration targets is available.
     *
     * @return bool True when the plugin is installed and active.
     */
    protected function is_integration_available() {
        return ThirdPartyIntegrationsUtil::is_integration_available( static::PLUGIN_FILE );
    }
}
