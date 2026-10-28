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
use FacebookPixelPlugin\Core\FacebookTrackingFacade;
use FacebookPixelPlugin\Core\FormFieldMapper;
use FacebookPixelPlugin\Core\ServerEventFactory;
use FacebookPixelPlugin\Utils\StringUtils;

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
     * Reads the form field mappings saved in wp-admin.
     *
     * @var FormFieldMapper
     */
    protected $field_mapper;

    /**
     * Constructor.
     *
     * @param FacebookTrackingFacade $signals      The tracking facade instance.
     * @param FormFieldMapper|null   $field_mapper Optional mapping storage.
     *                                             Defaults to the option-backed
     *                                             mapper.
     */
    public function __construct( FacebookTrackingFacade $signals, $field_mapper = null ) {
        parent::__construct( $signals );
        $this->field_mapper = $field_mapper instanceof FormFieldMapper
            ? $field_mapper
            : new FormFieldMapper();
    }

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
     * The integration's hard-coded fallback extraction always runs. If a
     * form-field -> lead-parameter mapping is configured for this form (see
     * is_mapping_available()/get_mapping_for_form()), it is applied on top: a
     * mapped field wins, and parameters the mapping does not cover keep their
     * fallback value. The arguments are whatever the plugin's submit hook
     * provides and are forwarded verbatim to the integration primitives.
     *
     * @param mixed ...$args The plugin submit-hook arguments.
     * @return array Normalized lead data keyed by Lead parameter name.
     */
    public function extract_lead_data( ...$args ) {
        $form_id = $this->get_form_id( ...$args );
        // Materialized because both the fallback and the mapping read the rows.
        $form_params = self::to_rows( $this->get_form_param_iterator( ...$args ) );
        $lead_data   = $this->extract_lead_data_fallback( $form_params );

        $mapping = $this->is_mapping_available( $form_id )
            ? $this->get_mapping_for_form( static::INTEGRATION_NAME, $form_id )
            : array();

        return empty( $mapping )
            ? $lead_data
            : array_merge( $lead_data, $this->apply_field_mapping( $form_params, $mapping ) );
    }

    /**
     * Applies a configured form-field -> lead-parameter mapping to the submitted
     * form parameters.
     *
     * A row is matched by its 'id' when it carries one (integrations whose
     * 'name' is a display label rather than a unique identifier add it), and by
     * its 'name' otherwise. Compound targets (FormFieldMapper::COMPOUND_TARGETS)
     * are expanded into their Lead parameters; unknown targets, empty values and
     * non-scalar values are skipped.
     *
     * @param iterable $form_param_iterator Rows of ['name'=>, 'type'=>, 'value'=>, ...].
     * @param array    $mapping             form_field_id => lead_parameter.
     * @return array Normalized lead data keyed by Lead parameter name.
     */
    protected function apply_field_mapping( $form_param_iterator, array $mapping ) {
        $result = array();
        foreach ( $form_param_iterator as $form_param ) {
            if ( empty( $form_param ) ) {
                continue;
            }
            $field_id = self::get_mapping_key( $form_param );
            if ( '' === $field_id || ! array_key_exists( $field_id, $mapping ) ) {
                continue;
            }
            $value = $form_param['value'];
            if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
                continue;
            }
            $lead_param = $mapping[ $field_id ];
            if ( isset( FormFieldMapper::COMPOUND_TARGETS[ $lead_param ] ) ) {
                $result = array_merge( $result, $this->expand_compound_lead_param( $lead_param, (string) $value ) );
                continue;
            }
            if ( ! $this->is_valid_lead_param( $lead_param ) ) {
                continue;
            }
            $result[ $lead_param ] = $value;
        }
        return $result;
    }

    /**
     * Expands a compound mapping target into the Lead parameters it stands for.
     *
     * @param string $compound The compound target (a FormFieldMapper::COMPOUND_TARGETS key).
     * @param string $value    The submitted field value.
     * @return array Lead data keyed by Lead parameter name.
     */
    protected function expand_compound_lead_param( $compound, $value ) {
        if ( 'full_name' !== $compound ) {
            return array();
        }
        $name = StringUtils::split_name( trim( $value ) );
        return array_filter(
            array(
                'first_name' => $name[0],
                'last_name'  => $name[1],
            ),
            function ( $part ) {
                return null !== $part && '' !== $part;
            }
        );
    }

    /**
     * Whether a form-field -> lead-parameter mapping is configured for the form.
     *
     * @param mixed $form_id The form identifier.
     * @return bool
     */
    protected function is_mapping_available( $form_id ) {
        if ( ! is_scalar( $form_id ) || '' === (string) $form_id ) {
            return false;
        }
        return ! empty( $this->get_mapping_for_form( static::INTEGRATION_NAME, $form_id ) );
    }

    /**
     * Returns the configured form-field -> lead-parameter mapping for a form.
     *
     * @param string $integration_name The integration name.
     * @param mixed  $form_id          The form identifier.
     * @return array form_field_id => lead_parameter.
     */
    protected function get_mapping_for_form( $integration_name, $form_id ) {
        return $this->field_mapper->get_mappings( $integration_name, (string) $form_id );
    }

    /**
     * The key a form-parameter row is matched on: its native field 'id' when
     * present, otherwise its 'name'.
     *
     * @param array $form_param A row from get_form_param_iterator().
     * @return string
     */
    protected static function get_mapping_key( $form_param ) {
        if ( isset( $form_param['id'] ) && '' !== (string) $form_param['id'] ) {
            return (string) $form_param['id'];
        }
        return isset( $form_param['name'] ) ? (string) $form_param['name'] : '';
    }

    /**
     * Normalizes what get_form_param_iterator() returned (a generator, an array
     * or nothing) into an array of rows.
     *
     * @param mixed $form_params The iterator result.
     * @return array
     */
    private static function to_rows( $form_params ) {
        if ( $form_params instanceof \Traversable ) {
            return iterator_to_array( $form_params, false );
        }
        return is_array( $form_params ) ? array_values( $form_params ) : array();
    }

    /**
     * Whether the form plugin is loaded and this integration can list its
     * forms for the field mapping screen. Integrations that implement the
     * discovery hooks override this with a check for the plugin's API.
     *
     * @return bool
     */
    public function is_field_mapping_available() {
        return false;
    }

    /**
     * Human-readable integration name for the field mapping screen.
     *
     * @return string
     */
    public function get_integration_label() {
        return static::INTEGRATION_NAME;
    }

    /**
     * Lists the plugin's forms for the field mapping screen.
     *
     * @return array<int,array<string,string>> Rows of ['id' =>, 'title' =>].
     */
    public function get_forms() {
        if ( ! $this->is_field_mapping_available() ) {
            return array();
        }

        $forms = array();
        foreach ( $this->fetch_forms() as $raw_form ) {
            $form = $this->map_form( $raw_form );
            if ( empty( $form['id'] ) ) {
                continue;
            }
            $forms[] = array(
                'id'    => (string) $form['id'],
                'title' => isset( $form['title'] ) ? (string) $form['title'] : '',
            );
        }
        return $forms;
    }

    /**
     * Lists a form's input fields for the field mapping screen. Each field id
     * is the key apply_field_mapping() matches submitted rows on (see
     * get_mapping_key()), so a saved mapping applies on submission.
     *
     * @param string $form_id The native form id.
     * @return array<int,array<string,string>> Rows of ['id' =>, 'label' =>, 'type' =>].
     */
    public function get_form_fields( $form_id ) {
        if ( ! $this->is_field_mapping_available() ) {
            return array();
        }

        $fields = array();
        foreach ( $this->fetch_form_fields( $form_id ) as $raw_field ) {
            $field = $this->map_field( $raw_field );
            if ( null === $field || ! isset( $field['id'] ) || '' === (string) $field['id'] ) {
                continue;
            }
            $id = (string) $field['id'];
            if ( isset( $fields[ $id ] ) ) {
                continue;
            }
            $label         = isset( $field['label'] ) ? trim( (string) $field['label'] ) : '';
            $fields[ $id ] = array(
                'id'    => $id,
                'label' => '' !== $label ? $label : $id,
                'type'  => isset( $field['type'] ) ? (string) $field['type'] : '',
            );
        }
        return array_values( $fields );
    }

    /**
     * Returns the plugin's raw forms.
     *
     * @return iterable
     */
    protected function fetch_forms() {
        return array();
    }

    /**
     * Maps a raw form to ['id' =>, 'title' =>].
     *
     * @param mixed $raw_form A raw form from fetch_forms().
     * @return array
     */
    protected function map_form( $raw_form ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Base placeholder; overridden by integrations.
        return array();
    }

    /**
     * Returns a form's raw fields.
     *
     * @param string $form_id The native form id.
     * @return iterable
     */
    protected function fetch_form_fields( $form_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Base placeholder; overridden by integrations.
        return array();
    }

    /**
     * Maps a raw field to ['id' =>, 'label' =>, 'type' =>], or null to skip
     * it (e.g. submit buttons and layout elements).
     *
     * @param mixed $raw_field A raw field from fetch_form_fields().
     * @return array|null
     */
    protected function map_field( $raw_field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Base placeholder; overridden by integrations.
        return null;
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
