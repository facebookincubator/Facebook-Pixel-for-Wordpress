<?php
/**
 * Facebook Pixel Plugin EventDataBuilder class.
 *
 * Bridge between the integration classes and the Signals dispatcher: it takes
 * an integration's extracted fields and produces a neutral EventData object.
 * It is the single place that knows how an integration's plain field names map
 * into the standard event payload. It lives outside Signals so that Signals
 * never learns an integration's data shape, and integrations never learn the
 * event payload shape.
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
 * Class EventDataBuilder
 *
 * Stateless builder: fields in, EventData out. (A fluent API is a planned
 * follow-up; kept array-based for now.)
 */
class EventDataBuilder {
    /**
     * Builds an EventData from a standard-keyed field array, dropping null and
     * empty-string values so downstream code doesn't emit sparse keys.
     *
     * @param array<string,mixed> $fields   Standard event fields.
     * @param string|null         $event_id Optional shared event id for
     *                                       browser/server deduplication.
     * @return EventData
     */
    public function build( array $fields, $event_id = null ) {
        $clean = array();
        foreach ( $fields as $key => $value ) {
            if ( null === $value || '' === $value ) {
                continue;
            }
            $clean[ $key ] = $value;
        }
        return new EventData( $clean, $event_id );
    }
}
