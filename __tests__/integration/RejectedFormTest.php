<?php
/**
 * Facebook Pixel Plugin RejectedFormTest class.
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

namespace FacebookPixelPlugin\Tests\Integration;

use FacebookPixelPlugin\Integration\FacebookWordpressContactForm7;
use FacebookPixelPlugin\Integration\FacebookWordpressFormidableForm;
use FacebookPixelPlugin\Integration\FacebookWordpressMailchimpForWp;
use FacebookPixelPlugin\Integration\FacebookWordpressNinjaForms;
use FacebookPixelPlugin\Integration\FacebookWordpressWPForms;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Tests\Mocks\MockContactForm7;

/**
 * A form whose mapping was rejected (saved with no fields) sends no Lead
 * event, in every lead-form integration. Each submission below would need
 * plugin APIs that are not mocked if the integration went on to read it.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RejectedFormTest extends FacebookWordpressTestBase {

    /**
     * Builds an integration whose form $form_id has a rejected mapping.
     *
     * @param string $class   The integration class.
     * @param string $form_id The rejected form's id.
     * @return \FacebookPixelPlugin\Integration\TrackableLeadFormIntegrationBase
     */
    private function make_rejected( $class, $form_id ) {
        $mapper = $this->make_field_mapper(
            array(
                $class::INTEGRATION_NAME => array(
                    $form_id => array(
                        'form_title' => 'Rejected',
                        'mappings'   => array(),
                    ),
                ),
            )
        );
        return new $class( $this->make_signals(), $mapper );
    }

    /**
     * Asserts nothing was delivered to CAPI or the browser.
     *
     * @return void
     */
    private function assert_no_lead() {
        $this->assertSame( array(), $this->captured_events );
        $this->assertSame( array(), $this->enqueued_events );
    }

    /**
     * Contact Form 7.
     *
     * @return void
     */
    public function testContactForm7() {
        $form = new MockContactForm7();
        $this->make_rejected( FacebookWordpressContactForm7::class, (string) $form->id() )
            ->capture_submitted_form( $form, array( 'status' => 'mail_sent' ) );
        $this->assert_no_lead();
    }

    /**
     * WPForms.
     *
     * @return void
     */
    public function testWPForms() {
        $this->make_rejected( FacebookWordpressWPForms::class, '5' )
            ->capture_submitted_form( array( 'fields' => array( '1' => 'a@b.co' ) ), array( 'id' => 5 ) );
        $this->assert_no_lead();
    }

    /**
     * Ninja Forms, whose hook is a filter: the actions come back unchanged.
     *
     * @return void
     */
    public function testNinjaForms() {
        $actions = array( array( 'settings' => array( 'type' => 'successmessage' ) ) );
        $result  = $this->make_rejected( FacebookWordpressNinjaForms::class, '2' )
            ->capture_submitted_form( $actions, array(), array( 'id' => 2 ) );
        $this->assertSame( $actions, $result );
        $this->assert_no_lead();
    }

    /**
     * Formidable Forms.
     *
     * @return void
     */
    public function testFormidable() {
        $this->make_rejected( FacebookWordpressFormidableForm::class, '9' )
            ->capture_submitted_form( 100, 9 );
        $this->assert_no_lead();
    }

    /**
     * Mailchimp for WP.
     *
     * @return void
     */
    public function testMailchimp() {
        $form       = new \stdClass();
        $form->ID   = 42;
        $form->data = array( 'EMAIL' => 'a@b.co' );
        $this->make_rejected( FacebookWordpressMailchimpForWp::class, '42' )
            ->capture_submitted_form( $form );
        $this->assert_no_lead();
    }
}
