<?php
/**
 * Facebook Pixel Plugin EventData class.
 *
 * Neutral, integration-agnostic representation of a tracked event's payload.
 * It is the only shape the Signals dispatcher consumes; integrations never
 * build it directly and Signals never sees an integration's raw data.
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
 * Class EventData
 *
 * Immutable value object holding the standard, integration-agnostic fields of
 * an event (both user-data and custom-data keys). Produced by
 * EventDataBuilder, consumed by Signals.
 */
class EventData {
    /**
     * Standard event fields keyed by standard field name.
     *
     * @var array<string,mixed>
     */
    private $fields;

    /**
     * Optional shared event id, used to deduplicate a browser event against its
     * server-side counterpart. Null when the dispatcher should generate one.
     *
     * @var string|null
     */
    private $event_id;

    /**
     * Constructor.
     *
     * @param array<string,mixed> $fields   Standard event fields.
     * @param string|null         $event_id Optional shared event id for
     *                                       browser/server deduplication.
     */
    public function __construct( array $fields = array(), $event_id = null ) {
        $this->fields   = $fields;
        $this->event_id = $event_id;
    }

    /**
     * Returns the shared event id, or null when none was provided.
     *
     * @return string|null
     */
    public function get_event_id() {
        return $this->event_id;
    }

    /**
     * Returns all fields as a standard-keyed array.
     *
     * @return array<string,mixed>
     */
    public function to_array() {
        return $this->fields;
    }

    /**
     * Returns a single field value.
     *
     * @param string $key      The standard field name.
     * @param mixed  $fallback Value to return when the field is absent.
     * @return mixed
     */
    public function get( $key, $fallback = null ) {
        return array_key_exists( $key, $this->fields )
            ? $this->fields[ $key ]
            : $fallback;
    }

    /**
     * Whether the payload carries no fields.
     *
     * @return bool
     */
    public function is_empty() {
        return empty( $this->fields );
    }
}
