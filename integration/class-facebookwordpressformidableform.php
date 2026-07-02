<?php
/**
 * Facebook Pixel Plugin FacebookWordpressFormidableForm class.
 *
 * This file contains the main logic for FacebookWordpressFormidableForm.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressFormidableForm class.
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

namespace FacebookPixelPlugin\Integration;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

use FacebookPixelPlugin\Core\FooterDelivery;

/**
 * FacebookWordpressFormidableForm class.
 */
class FacebookWordpressFormidableForm extends FacebookWordpressIntegrationBase {
  const PLUGIN_FILE   = 'formidable/formidable.php';
  const TRACKING_NAME = 'formidable-lite';

    /**
     * Registers the Formidable hook: a Lead event dispatched through Signals
     * after an entry is created, with the browser pixel emitted in the footer.
     *
     * @return void
     */
    public function inject_pixel_code() {
        $this->signals->on(
            'frm_after_create_entry',
            'Lead',
            array( $this, 'read_form_data' ),
            new FooterDelivery(),
            self::TRACKING_NAME,
            20,
            2
        );
    }

    /**
     * Extracts the Lead event data for a created Formidable entry.
     *
     * @param int      $entry_id The ID of the form entry.
     * @param int|null $form_id  The ID of the form (unused).
     * @return \FacebookPixelPlugin\Core\EventData|null
     */
    public function read_form_data( $entry_id, $form_id = null ) {
        if ( empty( $entry_id ) ) {
            return null;
        }

        $entry_values =
            IntegrationUtils::get_formidable_forms_entry_values( $entry_id );

        $field_values = $entry_values->get_field_values();
        if ( empty( $field_values ) ) {
            return null;
        }

        return $this->event_builder->build(
            array_merge(
                array(
                    'email'      => self::getEmail( $field_values ),
                    'first_name' => self::getFirstName( $field_values ),
                    'last_name'  => self::getLastName( $field_values ),
                    'phone'      => self::getPhone( $field_values ),
                ),
                self::getAddressInformation( $field_values )
            )
        );
    }

    /**
     * Retrieves the email address from the form data.
     *
     * @param array $field_values An associative array of field values.
     * @return string|null The email address, or null if
     * no email field is found.
     */
    private static function getEmail( $field_values ) {
        return self::getFieldValueByType( $field_values, 'email' );
    }

    /**
     * Retrieves the first name from the form data.
     *
     * This method extracts the first name field from the provided field values.
     *
     * @param array $field_values An associative array of field values.
     * @return string|null The first name, or null if no first
     * name field is found.
     */
    private static function getFirstName( $field_values ) {
        return self::getFieldValue( $field_values, 'text', 'Name', 'First' );
    }

    /**
     * Retrieves the last name from the form data.
     *
     * This method extracts the last name field from the provided field values.
     *
     * @param array $field_values An associative array of field values.
     * @return string|null The last name, or null if no
     * last name field is found.
     */
    private static function getLastName( $field_values ) {
        return self::getFieldValue( $field_values, 'text', 'Last', 'Last' );
    }

    /**
     * Retrieves the phone number from the form data.
     *
     * This method extracts the phone field from the provided field values.
     *
     * @param array $field_values An associative array of field values.
     * @return string|null The phone number, or null if no phone field is found.
     */
    private static function getPhone( $field_values ) {
        return self::getFieldValueByType( $field_values, 'phone' );
    }

    /**
     * Retrieves address information from the form data.
     *
     * This method extracts address information
     * (city, state, country, and zip code)
     * from the provided field values. It returns an
     * associative array containing
     * the extracted address information, or an empty
     * array if no address information
     * is found.
     *
     * @param array $field_values An associative array of field values.
     * @return array An associative array containing address information.
     */
    private static function getAddressInformation( $field_values ) {
        $address_saved_value = self::getFieldValueByType(
            $field_values,
            'address'
        );
        $address_data        = array();
        if ( $address_saved_value ) {
            if ( isset( $address_saved_value['city'] ) ) {
            $address_data['city'] = $address_saved_value['city'];
            }
            if ( isset( $address_saved_value['state'] ) ) {
                $address_data['state'] = $address_saved_value['state'];
            }
            if (
            isset( $address_saved_value['country'] )
            && strlen( $address_saved_value['country'] ) === 2
            ) {
                $address_data['country'] = $address_saved_value['country'];
            }
            if ( isset( $address_saved_value['zip'] ) ) {
                $address_data['zip'] = $address_saved_value['zip'];
            }
        }
        return $address_data;
    }

    /**
     * Retrieves the saved value of a specific field type
     * from the given field values.
     *
     * This method iterates through the provided field values to find a field
     * that matches the specified type. If a matching field is found, it returns
     * the saved value of that field. If no matching field
     * is found, it returns null.
     *
     * @param array  $field_values An array of field values.
     * @param string $type The type of the field to retrieve the value for.
     * @return mixed|null The saved value of the field,
     * or null if no matching field is found.
     */
    private static function getFieldValueByType( $field_values, $type ) {
        foreach ( $field_values as $field_value ) {
            $field = $field_value->get_field();
            if ( $field->type === $type ) {
            return $field_value->get_saved_value();
            }
        }

        return null;
    }

    /**
     * Retrieves the saved value of a field that matches the given criteria.
     *
     * This method iterates through the provided field
     * values to find a field that
     * matches the specified type, name, and description.
     * If a matching field is found,
     * it returns the saved value of that field. If no
     * matching field is found, it returns
     * null.
     *
     * @param array  $field_values An array of field values.
     * @param string $type The type of the field to retrieve the value for.
     * @param string $name The name of the field to retrieve the value for.
     * @param string $description The description of the
     * field to retrieve the value for.
     * @return mixed|null The saved value of the field,
     * or null if no matching field is found.
     */
    private static function getFieldValue(
        $field_values,
        $type,
        $name,
        $description
    ) {
        foreach ( $field_values as $field_value ) {
            $field = $field_value->get_field();
            if ( $field->type === $type &&
            $field->name === $name &&
            $field->description === $description ) {
            return $field_value->get_saved_value();
            }
        }

        return null;
    }
}
