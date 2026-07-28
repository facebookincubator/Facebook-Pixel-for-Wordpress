<?php
/**
 * Facebook Pixel Plugin Pixel class.
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

use FacebookPixelPlugin\FacebookAds\Object\ServerSide\CustomData;

/**
 * Handles browser-side Meta Pixel event tracking.
 *
 * Queues events during a request and renders the corresponding fbq() JavaScript
 * into the page, distinguishing standard events (track) from custom events
 * (trackCustom).
 */
class Pixel {

    /**
     * Browser events queued during the request, flushed to fbq() output.
     *
     * @var \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[]
     */
    private $browser_events = array();

    const EVENT_ID       = 'eventID';
    const TRACK          = 'track';
    const TRACK_CUSTOM   = 'trackCustom';
    const SCRIPT_TAG     =
    "<script type='text/javascript'>%s</script>";
    const FBQ_EVENT_CODE = "fbq('%s', '%s', %s, %s);";
    const FBQ_AGENT_CODE = "fbq('set', 'agent', '%s', '%s');";

    /**
     * The list of standard (normal) Meta Pixel events.
     *
     * Built from the event constants on FacebookPixel. Any event whose name is
     * not in this list is treated as a custom event (fired with `trackCustom`
     * instead of `track`).
     *
     * @var string[]
     */
    const NORMAL_EVENTS = array(
        FacebookPixel::ADDPAYMENTINFO,
        FacebookPixel::ADDTOCART,
        FacebookPixel::ADDTOWISHLIST,
        FacebookPixel::COMPLETEREGISTRATION,
        FacebookPixel::CONTACT,
        FacebookPixel::CUSTOMIZEPRODUCT,
        FacebookPixel::DONATE,
        FacebookPixel::FINDLOCATION,
        FacebookPixel::INITIATECHECKOUT,
        FacebookPixel::LEAD,
        FacebookPixel::PAGEVIEW,
        FacebookPixel::PURCHASE,
        FacebookPixel::SCHEDULE,
        FacebookPixel::SEARCH,
        FacebookPixel::STARTTRIAL,
        FacebookPixel::SUBMITAPPLICATION,
        FacebookPixel::SUBSCRIBE,
        FacebookPixel::VIEWCONTENT,
    );

    /**
     * The partner agent string.
     *
     * @var string
     */
    private $agent_string;

    /**
     * The Meta Pixel id.
     *
     * @var string
     */
    private $pixel_id;

    /**
     * Registered AJAX DOM elements (footer markup such as AJAX listener scripts
     * or cart-fragment placeholders) printed on wp_footer.
     *
     * @var string[]
     */
    private $ajax_dom_elements = array();

    /**
     * Stores the agent string and pixel id and registers the render hooks.
     *
     * @param string $agent_string The partner agent string.
     * @param string $pixel_id     The Meta Pixel id.
     */
    public function __construct( $agent_string, $pixel_id ) {
        $this->agent_string = $agent_string;
        $this->pixel_id     = $pixel_id;

        add_action( 'wp_head', array( $this, 'initialize' ) );
        add_action( 'wp_footer', array( $this, 'print_out_pending_dom_elements' ), 99 );
        add_action( 'wp_footer', array( $this, 'print_out_ajax_dom_elements' ), 99 );
    }

    /**
     * Initializes the pixel on wp_head.
     *
     * @return void
     */
    public function initialize() {
    }

    /**
     * Registers a footer AJAX DOM element to be printed on wp_footer.
     *
     * @param string $ajax_dom_element The element markup (e.g. an AJAX listener
     *                                 script or a cart-fragment placeholder div).
     * @return void
     */
    public function register_ajax_dom_element( $ajax_dom_element ) {
        $this->ajax_dom_elements[] = $ajax_dom_element;
    }

    /**
     * Prints all registered AJAX DOM elements into the page footer, verbatim.
     *
     * @return void
     */
    public function print_out_ajax_dom_elements() {
        $html = '';
        foreach ( $this->ajax_dom_elements as $ajax_dom_element ) {
            $html .= $ajax_dom_element;
        }
        printf(
            '%s',
            $html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pixel DOM is plugin-generated script, not user input.
        );
    }

    /**
     * Prints the queued pixel events into the page footer.
     *
     * Wraps the generated event script in Meta Pixel Event Code comment markers.
     *
     * @return void
     */
    public function print_out_pending_dom_elements() {
        $pixel_dom = $this->generate_script_for_tracked_events( true );
        printf(
            '
			<!-- Meta Pixel Event Code -->
			%s
			<!-- End Meta Pixel Event Code -->',
            $pixel_dom // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pixel DOM is plugin-generated script, not user input.
        );
    }

    /**
     * Queues an event to be rendered later in the page.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event to enqueue.
     * @return void
     */
    public function enqueue( $event ) {
        $this->browser_events[] = $event;
    }

    /**
     * Generates the fbq() JavaScript for a single event.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event              The event to render.
     * @param bool                                                     $include_script_tag Whether to wrap the output in a <script> tag. Default true.
     * @return string The generated script, or an empty string when the event is empty.
     */
    public function generate_script_for_event( $event, $include_script_tag ) {
        if ( empty( $event ) ) {
            return '';
        }

        $agent_setup_code = $this->generate_agent_setup_script();
        $fbq_for_events   = $this->generate_fbq_code_for_events( array( $event ) );

        $result = $agent_setup_code . $fbq_for_events;

        if ( $include_script_tag ) {
            $result = sprintf( self::SCRIPT_TAG, $result );
        }

        return $result;
    }

    /**
     * Generates the fbq() JavaScript for all queued events.
     *
     * Flushes the queued browser events after generating the script.
     *
     * @param bool $include_script_tag Whether to wrap the output in a <script> tag. Default true.
     * @return string The generated script, or an empty string when no events are queued.
     */
    public function generate_script_for_tracked_events( $include_script_tag ) {
        if ( empty( $this->browser_events ) ) {
            return '';
        }

        $agent_setup_code = $this->generate_agent_setup_script();
        $event_script     = '';
        if ( FacebookSignalState::is_held() ) {
            foreach ( $this->browser_events as $event ) {
                $event_script .= $this->generate_queue_code_for_event( $event );
            }
        } else {
            $event_script = $this->generate_fbq_code_for_events( $this->browser_events );
        }
        $this->flush_browser_events();

        $result = $agent_setup_code . $event_script;

        if ( $include_script_tag ) {
            $result = sprintf( self::SCRIPT_TAG, $result );
        }

        return $result;
    }

    /**
     * Generates the concatenated fbq() code for a list of events.
     *
     * Skips events whose event id has already been rendered to avoid duplicates.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event[] $events The events to render.
     * @return string The concatenated fbq() code.
     */
    private function generate_fbq_code_for_events( $events ) {
        $code      = '';
        $event_ids = array();
        foreach ( $events as $event ) {
            $event_id = $event->getEventId();
            if ( in_array( $event_id, $event_ids, true ) ) {
                continue;
            }
            $code       .= $this->generate_fbq_for_event( $event );
            $event_ids[] = $event_id;
        }
        return $code;
    }

    /**
     * Generates the fbq() agent setup code.
     *
     * @return string The generated agent setup code.
     */
    private function generate_agent_setup_script() {
        return sprintf(
            self::FBQ_AGENT_CODE,
            $this->agent_string,
            $this->pixel_id
        );
    }

    /**
     * Clears the queue of browser events.
     *
     * @return void
     */
    public function flush_browser_events() {
        $this->browser_events = array();
    }

    /**
     * Generates the fbq() code for a single event.
     *
     * Uses 'track' for standard events and 'trackCustom' for custom events, and
     * includes the normalized custom data and event id.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event to render.
     * @return string The generated fbq() code.
     */
    private function generate_fbq_for_event( $event ) {
        return sprintf(
            self::FBQ_EVENT_CODE,
            in_array( $event->getEventName(), self::NORMAL_EVENTS, true ) ?
                self::TRACK : self::TRACK_CUSTOM,
            $event->getEventName(),
            wp_json_encode( self::get_normalized_custom_data( $event ), JSON_PRETTY_PRINT ),
            wp_json_encode( array( self::EVENT_ID => $event->getEventId() ), JSON_PRETTY_PRINT )
        );
    }

    /**
     * Render the pixel event
     *
     * @param mixed $event The array of events, each event is an
     * array with the following keys:
     *                      - event_name: the name of the event
     *                      - event_id: the id of the event (optional)
     *                      - custom_data: the custom data
     * for the event (optional).
     * @param bool  $include_script_tag Whether to wrap the
     * generated code with a script tag.
     *
     * @return string The rendered pixel events
     */
    public function generate_queue_script_for_event(
        $event,
        $include_script_tag = true
    ) {
        if ( empty( $event ) ) {
            return '';
        }
        $code   = $this->generate_agent_setup_script();
        $result = $code . $this->generate_queue_code_for_event( $event );

        if ( $include_script_tag ) {
            $result = sprintf( self::SCRIPT_TAG, $result );
        }
        return $result;
    }

    /**
     * Generates the FacebookSignal.queueEvent() JavaScript for a held event.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @return string The generated queue script.
     */
    private function generate_queue_code_for_event( $event ) {
        // build_queue_payload() expects the custom data as an array; pass the
        // normalized custom_data (which already carries fb_integration_tracking)
        // and fold the event id in so it survives into the queued payload.
        $normalized_custom_data = self::get_normalized_custom_data( $event );
        if ( ! empty( $event->getEventId() ) ) {
            $normalized_custom_data[ self::EVENT_ID ] = $event->getEventId();
        }

        $payload               = FacebookPixel::build_queue_payload(
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
     * Returns the event's normalized custom data, falling back to an empty
     * CustomData when the event has none.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @return array The normalized custom data.
     */
    private static function get_normalized_custom_data( $event ) {
        // The fb_integration_tracking custom property is added to the event's
        // custom_data at event-creation time (see ServerEventFactory), so it is
        // already present in normalize() below — the Pixel layer no longer needs
        // to know the integration name.
        $normalized_custom_data = (
            $event->getCustomData() !== null ?
            $event->getCustomData() :
            new CustomData()
        )->normalize();

        return $normalized_custom_data;
    }
}
