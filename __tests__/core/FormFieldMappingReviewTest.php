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

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Core\FormFieldMappingReview;
use FacebookPixelPlugin\Integration\TrackableLeadFormIntegrationBase;
use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * FormFieldMappingReviewTest class.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class FormFieldMappingReviewTest extends FacebookWordpressTestBase {

    /**
     * An integration double with one form that was never reviewed.
     *
     * @return TrackableLeadFormIntegrationBase
     */
    private function integration() {
        $integration = \Mockery::mock( TrackableLeadFormIntegrationBase::class );
        $integration->shouldReceive( 'is_field_mapping_available' )->andReturn( true );
        $integration->shouldReceive( 'get_integration_label' )->andReturn( 'Test Forms' );
        $integration->shouldReceive( 'get_form_edit_url' )->andReturn( '' );
        $integration->shouldReceive( 'suggest_field_mapping' )->andReturn( array() );
        $integration->shouldReceive( 'get_forms' )->andReturn(
            array(
                array(
                    'id'    => '1',
                    'title' => 'Contact',
                ),
            )
        );
        $integration->shouldReceive( 'get_form_change_hooks' )->andReturn( array( 'test_forms_saved', 'deleted_post' ) );
        return $integration;
    }

    /**
     * Without a cached summary it is computed from the forms and cached.
     *
     * @return void
     */
    public function testComputesAndCachesTheSummary() {
        $integration = $this->integration();
        \WP_Mock::userFunction( 'get_transient', array( 'return' => false ) );
        \WP_Mock::userFunction(
            'set_transient',
            array(
                'times' => 1,
                'args'  => array(
                    FacebookPluginConfig::FIELD_MAPPING_REVIEW_TRANSIENT,
                    \Mockery::on(
                        function ( $summary ) {
                            return 1 === $summary['unreviewed'] && 0 === $summary['changed'];
                        }
                    ),
                    FacebookPluginConfig::FIELD_MAPPING_REVIEW_TTL,
                ),
            )
        );
        $review = new FormFieldMappingReview(
            function () use ( $integration ) {
                return array( 'test-forms' => $integration );
            },
            $this->make_field_mapper()
        );

        $summary = $review->get_summary();

        $this->assertSame( 1, $summary['unreviewed'] );
        $this->assertSame( md5( 'form:test-forms/1' ), $summary['signature'] );
    }

    /**
     * A cached summary is returned without listing any form.
     *
     * @return void
     */
    public function testUsesTheCachedSummary() {
        $cached = array(
            'unreviewed' => 3,
            'changed'    => 1,
            'signature'  => 'abc',
        );
        \WP_Mock::userFunction( 'get_transient', array( 'return' => $cached ) );
        $review = new FormFieldMappingReview(
            function () {
                throw new \Exception( 'The forms must not be listed.' );
            },
            $this->make_field_mapper()
        );

        $this->assertSame( $cached, $review->get_summary() );
    }

    /**
     * The cache is cleared on the generic change hooks and on each
     * integration's own hooks, once each.
     *
     * @return void
     */
    public function testRegistersTheInvalidationHooks() {
        $integration = $this->integration();
        foreach ( array_merge( FormFieldMappingReview::GENERIC_CHANGE_HOOKS, array( 'test_forms_saved' ) ) as $hook ) {
            \WP_Mock::expectActionAdded( $hook, array( FormFieldMappingReview::class, 'invalidate' ), 10, 0 );
        }

        FormFieldMappingReview::register_invalidation_hooks(
            function () use ( $integration ) {
                return array( 'test-forms' => $integration );
            }
        );

        $this->assertHooksAdded();
    }

    /**
     * Invalidating deletes the cached summary.
     *
     * @return void
     */
    public function testInvalidateDeletesTheCache() {
        \WP_Mock::userFunction(
            'delete_transient',
            array(
                'times' => 1,
                'args'  => array( FacebookPluginConfig::FIELD_MAPPING_REVIEW_TRANSIENT ),
            )
        );

        FormFieldMappingReview::invalidate();

        $this->assertConditionsMet();
    }
}
