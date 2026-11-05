<?php
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

namespace FacebookPixelPlugin\Tests\Integration;

use FacebookPixelPlugin\Core\FacebookTrackingFacade;
use FacebookPixelPlugin\Core\FormFieldMapper;
use FacebookPixelPlugin\Integration\FacebookWordpressContactForm7;
use FacebookPixelPlugin\Integration\TrackableLeadFormIntegrationBase;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7;

/**
 * Coverage for the form-field -> lead-parameter mapping path shared by the
 * TrackableLeadFormIntegrationBase subclasses.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class TrackableLeadFormMappingTest extends FacebookWordpressTestBase {

    /**
     * An address field submitted as parts expands into city, state, zip and a
     * two-letter country, whichever part names the plugin uses.
     *
     * @return void
     */
    public function testCompositeAddressMappingIsExpanded() {
        $integration = $this->make_integration(
            array(
                array(
                    'name'  => 'where',
                    'type'  => 'address',
                    'value' => array(
                        'address1' => '1 Main St',
                        'city'     => 'Springfield',
                        'state'    => 'OH',
                        'postal'   => '54321',
                        'country'  => 'us',
                    ),
                ),
            ),
            array(),
            array( 'where' => 'address' )
        );

        $this->assertSame(
            array(
                'city'    => 'Springfield',
                'state'   => 'OH',
                'zip'     => '54321',
                'country' => 'US',
            ),
            $integration->extract_lead_data( 7 )
        );

        $integration = $this->make_integration(
            array(
                array(
                    'name'  => 'where',
                    'type'  => 'address',
                    'value' => array(
                        'city'    => 'Springfield',
                        'country' => 'United States',
                    ),
                ),
            ),
            array(),
            array( 'where' => 'address' )
        );
        $this->assertSame( array( 'city' => 'Springfield' ), $integration->extract_lead_data( 7 ) );
    }

    /**
     * Builds a minimal lead-form integration whose submitted rows are $rows and
     * whose fallback extraction returns $fallback, with $mappings saved for
     * form 7.
     *
     * @param array $rows     Rows get_form_param_iterator() yields.
     * @param array $fallback What extract_lead_data_fallback() returns.
     * @param array|null $mappings Mappings saved for form 7 (field id => target),
     *                             or null when nothing is saved.
     * @return TrackableLeadFormIntegrationBase
     */
    private function make_integration( array $rows, array $fallback, $mappings ) {
        $mapper = $this->make_field_mapper(
            null === $mappings ? array() : array(
                'test-forms' => array(
                    '7' => array(
                        'form_title' => 'Test',
                        'mappings'   => $mappings,
                    ),
                ),
            )
        );

        return new class( $this->make_signals(), $mapper, $rows, $fallback ) extends TrackableLeadFormIntegrationBase {
            const INTEGRATION_NAME = 'test-forms';

            /**
             * Rows to yield.
             *
             * @var array
             */
            private $rows;

            /**
             * Fallback result.
             *
             * @var array
             */
            private $fallback;

            /**
             * Constructor.
             *
             * @param FacebookTrackingFacade $signals  Tracking facade.
             * @param FormFieldMapper        $mapper   Mapping storage.
             * @param array                  $rows     Rows to yield.
             * @param array                  $fallback Fallback result.
             */
            public function __construct( FacebookTrackingFacade $signals, FormFieldMapper $mapper, array $rows, array $fallback ) {
                parent::__construct( $signals, $mapper );
                $this->rows     = $rows;
                $this->fallback = $fallback;
            }

            /**
             * No hooks needed.
             *
             * @return void
             */
            protected function set_up_tracking() {}

            /**
             * The form id is the first hook argument.
             *
             * @param mixed ...$args Hook arguments.
             * @return mixed
             */
            protected function get_form_id( ...$args ) {
                return $args[0];
            }

            /**
             * Yields the configured rows.
             *
             * @param mixed ...$args Hook arguments.
             * @return iterable
             */
            protected function get_form_param_iterator( ...$args ) {
                foreach ( $this->rows as $row ) {
                    yield $row;
                }
            }

            /**
             * Returns the configured fallback.
             *
             * @param iterable $form_param_iterator Rows.
             * @return array
             */
            protected function extract_lead_data_fallback( $form_param_iterator ) {
                return $this->fallback;
            }
        };
    }

    /**
     * Without a saved mapping the fallback extraction is returned as is.
     *
     * @return void
     */
    public function testNoMappingUsesFallback() {
        $integration = $this->make_integration(
            array( array( 'name' => 'mail', 'type' => 'email', 'value' => 'a@b.co' ) ),
            array( 'email' => 'a@b.co' ),
            null
        );

        $this->assertSame( array( 'email' => 'a@b.co' ), $integration->extract_lead_data( 7 ) );
    }

    /**
     * A rejected suggestion (a saved mapping with no fields) sends no customer
     * information; it does not fall back to automatic detection.
     *
     * @return void
     */
    public function testRejectedMappingSendsNothing() {
        $integration = $this->make_integration(
            array( array( 'name' => 'mail', 'type' => 'email', 'value' => 'a@b.co' ) ),
            array( 'email' => 'a@b.co' ),
            array()
        );

        $this->assertSame( array(), $integration->extract_lead_data( 7 ) );
    }

    /**
     * A saved mapping replaces automatic detection: only mapped fields are
     * sent, even when the fallback would have found more.
     *
     * @return void
     */
    public function testSavedMappingReplacesFallback() {
        $integration = $this->make_integration(
            array(
                array( 'name' => 'work-email', 'type' => 'text', 'value' => 'work@b.co' ),
                array( 'name' => 'mail', 'type' => 'email', 'value' => 'home@b.co' ),
            ),
            array(
                'email' => 'home@b.co',
                'phone' => '555',
            ),
            array( 'work-email' => 'email' )
        );

        $this->assertSame( array( 'email' => 'work@b.co' ), $integration->extract_lead_data( 7 ) );
    }

    /**
     * A mapping saved for another form is not applied.
     *
     * @return void
     */
    public function testMappingIsPerForm() {
        $integration = $this->make_integration(
            array( array( 'name' => 'work-email', 'type' => 'text', 'value' => 'work@b.co' ) ),
            array(),
            array( 'work-email' => 'email' )
        );

        $this->assertSame( array(), $integration->extract_lead_data( 8 ) );
    }

    /**
     * A full_name target is split into first and last name; a single word
     * becomes the first name only, and a name submitted as parts uses its
     * first and last parts.
     *
     * @return void
     */
    public function testFullNameIsSplit() {
        $mappings = array( 'who' => 'full_name' );

        $integration = $this->make_integration(
            array( array( 'name' => 'who', 'type' => 'text', 'value' => ' Pika Chu ' ) ),
            array(),
            $mappings
        );
        $this->assertSame(
            array(
                'first_name' => 'Pika',
                'last_name'  => 'Chu',
            ),
            $integration->extract_lead_data( 7 )
        );

        $integration = $this->make_integration(
            array( array( 'name' => 'who', 'type' => 'text', 'value' => 'Pikachu' ) ),
            array(),
            $mappings
        );
        $this->assertSame( array( 'first_name' => 'Pikachu' ), $integration->extract_lead_data( 7 ) );
        $integration = $this->make_integration(
            array(
                array(
                    'name'  => 'who',
                    'type'  => 'name',
                    'value' => array(
                        'first'  => 'Pika',
                        'middle' => 'P',
                        'last'   => 'Chu',
                    ),
                ),
            ),
            array(),
            $mappings
        );
        $this->assertSame(
            array(
                'first_name' => 'Pika',
                'last_name'  => 'Chu',
            ),
            $integration->extract_lead_data( 7 )
        );
    }

    /**
     * Rows carrying a native field id are matched by it rather than by their
     * display name.
     *
     * @return void
     */
    public function testRowsAreMatchedByIdBeforeName() {
        $integration = $this->make_integration(
            array(
                array( 'name' => 'email', 'type' => 'email', 'value' => 'by-name@b.co', 'id' => '3' ),
                array( 'name' => 'email', 'type' => 'email', 'value' => 'by-id@b.co', 'id' => '4' ),
            ),
            array(),
            array(
                'email' => 'email',
                '4'     => 'email',
            )
        );

        $this->assertSame( array( 'email' => 'by-id@b.co' ), $integration->extract_lead_data( 7 ) );
    }

    /**
     * Invalid targets, empty values and non-scalar values are skipped.
     *
     * @return void
     */
    public function testInvalidTargetsAndValuesAreSkipped() {
        $integration = $this->make_integration(
            array(
                array( 'name' => 'a', 'type' => 'text', 'value' => 'x' ),
                array( 'name' => 'b', 'type' => 'text', 'value' => '  ' ),
                array( 'name' => 'c', 'type' => 'name', 'value' => array( 'first' => 'P' ) ),
            ),
            array(),
            array(
                'a' => 'company',
                'b' => 'phone',
                'c' => 'first_name',
            )
        );

        $this->assertSame( array(), $integration->extract_lead_data( 7 ) );
    }

    /**
     * A mapping saved for a Contact Form 7 form is applied on submission: the
     * integration looks it up under its INTEGRATION_NAME and the form's id.
     *
     * @return void
     */
    public function testContactForm7AppliesSavedMapping() {
        \WP_Mock::userFunction(
            'sanitize_text_field',
            array(
                'return' => function ( $input ) {
                    return $input;
                },
            )
        );
        $form = new MockContactForm7();
        $form->add_tag( 'email', 'your-email', 'pika.chu@s2s.com' );
        $form->add_tag( 'text', 'contact', 'Ash Ketchum' );

        $mapper      = $this->make_field_mapper(
            array(
                FacebookWordpressContactForm7::INTEGRATION_NAME => array(
                    (string) $form->id() => array(
                        'form_title' => 'Contact',
                        'mappings'   => array( 'contact' => 'full_name' ),
                    ),
                ),
            )
        );
        $integration = new FacebookWordpressContactForm7( $this->make_signals(), $mapper );

        // The email tag is not mapped, so the saved mapping leaves it out.
        $this->assertSame(
            array(
                'first_name' => 'Ash',
                'last_name'  => 'Ketchum',
            ),
            $integration->extract_lead_data( $form )
        );
    }
}
