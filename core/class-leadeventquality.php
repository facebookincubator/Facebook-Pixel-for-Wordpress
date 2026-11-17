<?php
/**
 * Facebook Pixel Plugin LeadEventQuality class.
 *
 * Reads the Event Match Quality of the pixel's Lead events from Meta's
 * Dataset Quality API: its score (0-10) and the customer information
 * parameters received with them, with the share of events that include each.
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
 * Class LeadEventQuality
 *
 * @see https://developers.facebook.com/docs/marketing-api/conversions-api/dataset-quality-api
 */
class LeadEventQuality {
    const STATUS_OK            = 'ok';
    const STATUS_NO_DATA       = 'no_data';
    const STATUS_NOT_CONNECTED = 'not_connected';
    const STATUS_ERROR         = 'error';

    /**
     * The event whose quality is read.
     *
     * @var string
     */
    const EVENT_NAME = 'Lead';

    /**
     * Transient caching the last answer, with the pixel it is for.
     *
     * @var string
     */
    const CACHE_KEY = 'facebook_lead_event_quality';

    /**
     * Seconds an answer is cached (a day), unless a form mapping changes
     * first (see clear_cache()). A failed request is retried sooner.
     *
     * @var int
     */
    const CACHE_TTL       = 86400;
    const ERROR_CACHE_TTL = 3600;

    /**
     * The fields asked for: the score and parameters of every web event.
     *
     * @var string
     */
    const FIELDS = 'web{event_name,event_match_quality{composite_score,match_key_feedback{identifier,coverage{percentage}}}}';

    /**
     * Names for the identifiers Meta reports. The API documents the
     * identifier as an open string, so others are shown humanized.
     *
     * @var array<string,string>
     */
    const PARAMETER_LABELS = array(
        'email'         => 'Email',
        'phone'         => 'Phone',
        'first_name'    => 'First name',
        'last_name'     => 'Last name',
        'external_id'   => 'External ID',
        'ip_address'    => 'IP address',
        'user_agent'    => 'User agent',
        'click_id'      => 'Click ID (fbc)',
        'fbc'           => 'Click ID (fbc)',
        'browser_id'    => 'Browser ID (fbp)',
        'fbp'           => 'Browser ID (fbp)',
        'city'          => 'City',
        'state'         => 'State',
        'zip'           => 'ZIP / postal code',
        'zip_code'      => 'ZIP / postal code',
        'country'       => 'Country',
        'date_of_birth' => 'Date of birth',
        'gender'        => 'Gender',
        'fb_login_id'   => 'Facebook Login ID',
        'lead_id'       => 'Lead ID',
    );

    /**
     * The pixel (dataset) id.
     *
     * @var string
     */
    private $pixel_id;

    /**
     * Access token with access to the pixel.
     *
     * @var string
     */
    private $access_token;

    /**
     * Constructor.
     *
     * @param string $pixel_id     The pixel (dataset) id.
     * @param string $access_token Access token with access to the pixel.
     */
    public function __construct( $pixel_id, $access_token ) {
        $this->pixel_id     = (string) $pixel_id;
        $this->access_token = (string) $access_token;
    }

    /**
     * For the pixel and token the plugin sends events with.
     *
     * @return LeadEventQuality
     */
    public static function from_options() {
        return new self(
            FacebookWordpressOptions::get_active_pixel_id(),
            FacebookWordpressOptions::get_active_access_token()
        );
    }

    /**
     * The quality of the Lead events, cached for a day or until a form
     * mapping changes.
     *
     * @return array [ 'status' =>, 'score' => float|null, 'rating' => string|null,
     *               'parameters' => [ [ 'identifier', 'label', 'coverage' ] ],
     *               'message' => string ]
     */
    public function get() {
        if ( '' === $this->pixel_id || '' === $this->access_token ) {
            return self::result( self::STATUS_NOT_CONNECTED );
        }

        $cached = get_transient( self::CACHE_KEY );
        if ( is_array( $cached ) && isset( $cached['pixel_id'], $cached['result'] )
            && $cached['pixel_id'] === $this->pixel_id ) {
            return $cached['result'];
        }

        $result = $this->fetch();
        set_transient(
            self::CACHE_KEY,
            array(
                'pixel_id' => $this->pixel_id,
                'result'   => $result,
            ),
            self::STATUS_ERROR === $result['status'] ? self::ERROR_CACHE_TTL : self::CACHE_TTL
        );
        return $result;
    }

    /**
     * Asks the Dataset Quality API.
     *
     * @return array See get().
     */
    private function fetch() {
        $url = add_query_arg(
            array(
                'dataset_id' => rawurlencode( $this->pixel_id ),
                'fields'     => rawurlencode( self::FIELDS ),
            ),
            'https://graph.facebook.com/' . FacebookPluginConfig::GRAPH_API_VERSION . '/dataset_quality'
        );

        $response = wp_remote_get(
            $url,
            array(
                'headers' => array( 'Authorization' => 'Bearer ' . $this->access_token ),
                'timeout' => 15,
            )
        );
        if ( is_wp_error( $response ) ) {
            return self::result( self::STATUS_ERROR, null, array(), $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( wp_remote_retrieve_response_code( $response ) >= 400 || ! is_array( $body ) || isset( $body['error'] ) ) {
            $message = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : '';
            return self::result( self::STATUS_ERROR, null, array(), $message );
        }

        return self::parse( $body );
    }

    /**
     * Picks the Lead event out of a Dataset Quality API response.
     *
     * @param array $body The decoded response.
     * @return array See get().
     */
    public static function parse( $body ) {
        $events = isset( $body['web'] ) && is_array( $body['web'] ) ? $body['web'] : array();
        foreach ( $events as $event ) {
            if ( ! is_array( $event ) || ! isset( $event['event_name'] )
                || self::EVENT_NAME !== $event['event_name']
                || ! isset( $event['event_match_quality']['composite_score'] )
                || ! is_numeric( $event['event_match_quality']['composite_score'] ) ) {
                continue;
            }
            $quality = $event['event_match_quality'];
            $score   = round( max( 0, min( 10, (float) $quality['composite_score'] ) ), 1 );

            $parameters = array();
            $feedback   = isset( $quality['match_key_feedback'] ) && is_array( $quality['match_key_feedback'] )
                ? $quality['match_key_feedback'] : array();
            foreach ( $feedback as $key ) {
                if ( ! isset( $key['identifier'] ) || ! is_string( $key['identifier'] ) || '' === $key['identifier'] ) {
                    continue;
                }
                $coverage     = isset( $key['coverage']['percentage'] ) && is_numeric( $key['coverage']['percentage'] )
                    ? round( max( 0, min( 100, (float) $key['coverage']['percentage'] ) ), 1 )
                    : null;
                $parameters[] = array(
                    'identifier' => $key['identifier'],
                    'label'      => self::parameter_label( $key['identifier'] ),
                    'coverage'   => $coverage,
                );
            }
            // Most included first.
            usort(
                $parameters,
                function ( $a, $b ) {
                    return (float) $b['coverage'] <=> (float) $a['coverage'];
                }
            );

            return self::result( self::STATUS_OK, $score, $parameters );
        }
        return self::result( self::STATUS_NO_DATA );
    }

    /**
     * Drops the cached answer, so the next get() asks Meta again.
     */
    public static function clear_cache() {
        delete_transient( self::CACHE_KEY );
    }

    /**
     * The name shown for a match key identifier.
     *
     * @param string $identifier E.g. ip_address.
     * @return string
     */
    public static function parameter_label( $identifier ) {
        if ( isset( self::PARAMETER_LABELS[ $identifier ] ) ) {
            return self::PARAMETER_LABELS[ $identifier ];
        }
        return ucfirst( str_replace( '_', ' ', $identifier ) );
    }

    /**
     * The rating Events Manager gives a score: Poor (0-2), OK (3-5),
     * Good (6-7) or Great (8-10).
     *
     * @param float $score 0-10.
     * @return string poor|ok|good|great
     */
    public static function rating( $score ) {
        if ( $score >= 8 ) {
            return 'great';
        }
        if ( $score >= 6 ) {
            return 'good';
        }
        if ( $score >= 3 ) {
            return 'ok';
        }
        return 'poor';
    }

    /**
     * A result row.
     *
     * @param string     $status     One of the STATUS_ constants.
     * @param float|null $score      0-10, with STATUS_OK.
     * @param array      $parameters Parameters received, with STATUS_OK.
     * @param string     $message    Meta's error message, with STATUS_ERROR.
     * @return array
     */
    private static function result( $status, $score = null, $parameters = array(), $message = '' ) {
        return array(
            'status'     => $status,
            'score'      => $score,
            'rating'     => null === $score ? null : self::rating( $score ),
            'parameters' => $parameters,
            'message'    => $message,
        );
    }
}
