<?php
/**
 * Facebook Pixel Plugin LeadFormIntegrationTrait trait.
 *
 * This file contains shared logic for lead-form plugin integrations.
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

use FacebookPixelPlugin\Core\FacebookPixel;
use FacebookPixelPlugin\Core\ServerEventFactory;

/**
 * Base class for lead-form plugin integrations that validate and extract Meta
 * Lead event data from submitted forms.
 */
abstract class TrackableLeadFormIntegrationBase extends TrackableIntegrationBase {


    /**
     * The Meta event these lead-form integrations fire.
     *
     * @var string
     */
    const EVENT_NAME = FacebookPixel::LEAD;

    /**
     * The Lead event parameters a form field may be mapped to.
     *
     * Derived from ServerEventFactory's user-data field map (the single source
     * of truth for which extracted keys become hashed customer-matching
     * user_data vs. falling through to custom_data). Deriving — rather than
     * duplicating the list — keeps this validator from drifting when fields are
     * added there.
     *
     * TODO(transport-divergence): these are the CAPI/user_data field names. Form
     * lead data currently reaches Meta only via CAPI, so a single list is
     * correct. If form data is ever added to the browser Pixel's advanced
     * matching, revisit whether Pixel-vs-CAPI parameter naming needs separate
     * validation. (Follow-up owned by the team.)
     *
     * @return string[]
     */
    protected static function get_valid_lead_parameters() {
        return ServerEventFactory::get_user_data_field_names();
    }

    /**
     * Strong customer-matching identifiers. A valid Lead payload must carry at
     * least one of these non-empty so the event is actually matchable by Meta;
     * geo-only data (city/state/zip) is not sufficient on its own.
     *
     * @var string[]
     */
    const STRONG_IDENTIFIERS = array(
        'email',
        'phone',
    );

    /**
     * Sentinel returned by validate_lead_fields() when no strong identifier is
     * present. It is not a form field; it flags the absence of one.
     *
     * @var string
     */
    const MISSING_STRONG_IDENTIFIER = '__missing_strong_identifier__';

    /**
     * Validates a set of resolved lead fields against the parameters supported
     * by a Meta Lead event.
     *
     * Empty values are ignored so partially filled forms still validate on the
     * fields that were provided. A field set is valid (empty return) only when
     * every non-empty key is a recognized Lead parameter
     * (see get_valid_lead_parameters()) with a valid value, and at least one
     * strong identifier (see STRONG_IDENTIFIERS) is present.
     *
     * @param array $fields Associative array of lead data keyed by parameter
     *                      name (parameter_name => value), e.g. the output of
     *                      get_lead_data().
     * @return string[] The offending keys: any unrecognized parameter names,
     *                  any recognized parameter with an invalid value, plus
     *                  self::MISSING_STRONG_IDENTIFIER when no strong identifier
     *                  is provided. An empty array means the payload is valid.
     */
    public function validate_lead_fields( array $fields ) {
        $provided = array_filter(
            $fields,
            function ( $value ) {
                return null !== $value && '' !== $value;
            }
        );

        $offending_keys = array();

        foreach ( $provided as $parameter => $value ) {
            if ( ! $this->is_valid_lead_param( $parameter ) ) {
                $offending_keys[] = $parameter;
                continue;
            }
            if ( ! $this->is_valid_lead_value( $parameter, $value ) ) {
                $offending_keys[] = $parameter;
            }
        }

        $has_strong_identifier = false;
        foreach ( self::STRONG_IDENTIFIERS as $identifier ) {
            if ( ! empty( $provided[ $identifier ] ) ) {
                $has_strong_identifier = true;
                break;
            }
        }

        if ( ! $has_strong_identifier ) {
            $offending_keys[] = self::MISSING_STRONG_IDENTIFIER;
        }

        return $offending_keys;
    }

    /**
     * Whether a single parameter name is a recognized Lead parameter.
     *
     * @param string $param_name The parameter name to check.
     * @return bool
     */
    public function is_valid_lead_param( $param_name ) {
        return in_array( $param_name, self::get_valid_lead_parameters(), true );
    }

    /**
     * Light value-level sanity check for a recognized Lead parameter, mirroring
     * the constraints the SDK Normalizer enforces (which would otherwise throw
     * and cause the whole event to be dropped). Fails fast so callers can report
     * the offending field instead of silently losing the event.
     *
     * @param string $param_name The recognized Lead parameter name.
     * @param mixed  $value      The value to check.
     * @return bool
     */
    protected function is_valid_lead_value( $param_name, $value ) {
        switch ( $param_name ) {
            case 'country':
                // ISO 3166-1 alpha-2: exactly two letters.
                return is_string( $value )
                    && 1 === preg_match( '/^[A-Za-z]{2}$/', trim( $value ) );
            default:
                return true;
        }
    }

    /**
     * Extracts normalized Lead data from a submitted form.
     *
     * If a form-field -> lead-parameter mapping is configured for this form
     * (see is_mapping_available()/get_mapping_for_form()), that mapping is
     * applied. Otherwise the integration's hard-coded fallback extraction is
     * used. The arguments are whatever the plugin's submit hook provides and are
     * forwarded verbatim to the integration primitives.
     *
     * @param mixed ...$args The plugin submit-hook arguments.
     * @return array Normalized lead data keyed by Lead parameter name.
     */
    public function extract_lead_data( ...$args ) {
        $form_id  = $this->get_form_id( ...$args );
        $iterator = $this->get_form_param_iterator( ...$args );

        $mapping = $this->is_mapping_available( $form_id )
            ? $this->get_mapping_for_form( static::INTEGRATION_NAME, $form_id )
            : array();

        return empty( $mapping )
            ? $this->extract_lead_data_fallback( $iterator )
            : $this->apply_field_mapping( $iterator, $mapping );
    }

    /**
     * Applies a configured form-field -> lead-parameter mapping to the submitted
     * form parameters.
     *
     * @param iterable $form_param_iterator Rows of ['name'=>, 'type'=>, 'value'=>, ...].
     * @param array    $mapping             form_field_name => lead_parameter.
     * @return array Normalized lead data keyed by Lead parameter name.
     */
    protected function apply_field_mapping( $form_param_iterator, array $mapping ) {
        $result = array();
        foreach ( $form_param_iterator as $form_param ) {
            if ( empty( $form_param ) ) {
                continue;
            }
            $field_name = $form_param['name'];
            if ( ! array_key_exists( $field_name, $mapping ) ) {
                continue;
            }
            $lead_param = $mapping[ $field_name ];
            if ( ! $this->is_valid_lead_param( $lead_param ) ) {
                continue;
            }
            // TODO(mapping-UI): no notion of compound params yet. A compound
            // target like 'address' is not in the valid-parameter set (Meta has
            // no 'address' field; only city/state/zip/country), so it is rejected
            // above and silently dropped here. When the mapping UI lands, add a
            // compound concept (e.g. COMPOUND_LEAD_PARAMETERS: address =>
            // [city,state,zip,country]), make is_valid_lead_param /
            // validate_lead_fields treat compound keys as valid *input*, and
            // decompose the value here (per-integration hook) into leaf params so
            // only leaves reach the payload. Completes TrackableLeadFormMappingTest.
            $result[ $lead_param ] = $form_param['value'];
        }
        return $result;
    }

    /**
     * Whether a form-field -> lead-parameter mapping is configured for the form.
     *
     * @param mixed $form_id The form identifier.
     * @return bool
     */
    protected function is_mapping_available( $form_id ) {
        // TODO: read from the mapping storage once the mapping UI exists.
        return false;
    }

    /**
     * Returns the configured form-field -> lead-parameter mapping for a form.
     *
     * @param string $integration_name The integration name.
     * @param mixed  $form_id          The form identifier.
     * @return array form_field_name => lead_parameter.
     */
    protected function get_mapping_for_form( $integration_name, $form_id ) {
        // TODO: read from the mapping storage once the mapping UI exists.
        return array();
    }

    /**
     * Reads the form id from the plugin submit-hook arguments.
     *
     * @param mixed ...$args The plugin submit-hook arguments.
     * @return mixed The form identifier.
     */
    abstract protected function get_form_id( ...$args );

    /**
     * Yields the submitted form parameters as normalized rows, each the output
     * of get_iterator_yield_output(): ['name'=>, 'type'=>, 'value'=>, ...extras].
     *
     * @param mixed ...$args The plugin submit-hook arguments.
     * @return iterable
     */
    abstract protected function get_form_param_iterator( ...$args );

    /**
     * Hard-coded extraction used when no mapping is configured for the form.
     *
     * @param iterable $form_param_iterator Rows of ['name'=>, 'type'=>, 'value'=>, ...].
     * @return array Normalized lead data keyed by Lead parameter name.
     */
    abstract protected function extract_lead_data_fallback( $form_param_iterator );

    /**
     * Enriches a form plugin's AJAX response with the Lead pixel code under the
     * key the footer listener reads, so the browser can eval it. An
     * already-populated key (or a non-array response) is left untouched.
     *
     * @param mixed  $response     The plugin AJAX response.
     * @param string $pixel_code   The pixel script to inject.
     * @param string $response_key The response key the footer listener reads.
     * @return mixed The (possibly enriched) response.
     */
    public function add_tracking_code_to_ajax_response( $response, $pixel_code, $response_key ) {
        if ( is_array( $response ) && ! isset( $response[ $response_key ] ) ) {
            $response[ $response_key ] = $pixel_code;
        }
        return $response;
    }

    /**
     * Builds a normalized form-parameter row from the given field values. The
     * name, type and value keys are always present (possibly empty); any extra
     * key/value pairs are appended in order.
     *
     * @param string $name    The form field name.
     * @param string $type    The form field type.
     * @param mixed  $value   The submitted field value.
     * @param mixed  ...$rest Optional extra key/value pairs appended in order.
     * @return array The normalized row keyed by 'name', 'type', 'value' and any extras.
     */
    protected static function get_iterator_yield_output( $name, $type, $value, ...$rest ) {
        // name/type/value are always present (possibly empty) so consumers can
        // read them without existence checks; an empty name/type simply matches
        // nothing downstream, preserving type-based extraction.
        $result = array(
            'name'  => $name,
            'type'  => $type,
            'value' => $value,
        );
        if ( ! empty( $rest ) ) {
            $count = count( $rest );
            for ( $i = 0; $i < $count && $i + 1 < $count; $i += 2 ) {
                $result[ $rest[ $i ] ] = $rest[ $i + 1 ];
            }
        }
        return $result;
    }
}
