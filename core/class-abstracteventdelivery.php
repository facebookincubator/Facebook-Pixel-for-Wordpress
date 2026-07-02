<?php
/**
 * Facebook Pixel Plugin AbstractEventDelivery class.
 *
 * Shared base for delivery strategies: it queues the events dispatched for its
 * on() registration and renders exactly those. Subclasses implement register()
 * to decide where the rendered code is emitted.
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
 * Class AbstractEventDelivery
 */
abstract class AbstractEventDelivery implements EventDelivery {
    /**
     * Integration tracking name used when rendering.
     *
     * @var string
     */
    protected $tracking_name = '';

    /**
     * Events queued for this delivery.
     *
     * @var array
     */
    protected $events = array();

    /**
     * Queues a dispatched event to be rendered by this delivery.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @return void
     */
    public function queue( $event ) {
        $this->store( $event );
    }

    /**
     * Records an event without any delivery side effect. Subclasses that emit
     * on queue() (e.g. inline enqueue) use this to keep the event for later
     * rendering without triggering that side effect.
     *
     * @param \FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event $event The event.
     * @return void
     */
    protected function store( $event ) {
        $this->events[] = $event;
    }

    /**
     * Whether any events are queued.
     *
     * @return bool
     */
    protected function has_events() {
        return ! empty( $this->events );
    }

    /**
     * Renders this delivery's queued events to browser pixel code.
     *
     * @param bool $script_tag Whether to wrap in a <script> tag.
     * @return string
     */
    protected function render_code( $script_tag = true ) {
        return PixelRenderer::render(
            $this->events,
            $this->tracking_name,
            $script_tag
        );
    }
}
