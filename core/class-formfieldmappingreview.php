<?php
/**
 * Facebook Pixel Plugin FormFieldMappingReview class.
 *
 * What the form field mapping admin notice reports, cached so admin pages
 * do not list every form and its fields on each load.
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
 * Class FormFieldMappingReview
 *
 * The summary (FormFieldMappingOverview::summarize_review()) is the same for
 * every user, so it is kept in a transient. It is cleared whenever it may
 * change: a mapping is saved or reset, a form plugin saves or deletes a form
 * or field (each integration lists its hooks, see
 * TrackableLeadFormIntegrationBase::get_form_change_hooks()), a post is
 * deleted or restored, or a plugin is activated or deactivated. The
 * transient also expires, for changes no hook reports (e.g. direct database
 * edits).
 */
class FormFieldMappingReview {
    /**
     * Hooks that may change the review for any plugin: forms stored as posts
     * can be trashed, restored or deleted, and activating or deactivating a
     * form plugin changes which forms are listed.
     *
     * @var string[]
     */
    const GENERIC_CHANGE_HOOKS = array(
        'trashed_post',
        'untrashed_post',
        'deleted_post',
        'activated_plugin',
        'deactivated_plugin',
    );

    /**
     * Returns the lead-form integrations that support field mapping, keyed
     * by INTEGRATION_NAME.
     *
     * @var callable
     */
    private $integrations;

    /**
     * Form field mapping storage.
     *
     * @var FormFieldMapper
     */
    private $mapper;

    /**
     * Constructor.
     *
     * @param callable        $integrations Returns the lead-form integrations
     *                                      that support field mapping.
     * @param FormFieldMapper $mapper       Mapping storage.
     */
    public function __construct( callable $integrations, FormFieldMapper $mapper ) {
        $this->integrations = $integrations;
        $this->mapper       = $mapper;
    }

    /**
     * The review summary, from the cache when it is there.
     *
     * @return array{unreviewed: int, changed: int, signature: string}
     */
    public function get_summary() {
        $cached = get_transient( FacebookPluginConfig::FIELD_MAPPING_REVIEW_TRANSIENT );
        if ( is_array( $cached ) && isset( $cached['unreviewed'], $cached['changed'], $cached['signature'] ) ) {
            return $cached;
        }
        $summary = FormFieldMappingOverview::summarize_review(
            FormFieldMappingOverview::build( call_user_func( $this->integrations ), $this->mapper )
        );
        set_transient(
            FacebookPluginConfig::FIELD_MAPPING_REVIEW_TRANSIENT,
            $summary,
            FacebookPluginConfig::FIELD_MAPPING_REVIEW_TTL
        );
        return $summary;
    }

    /**
     * Clears the cached summary, so the next admin page that needs it
     * computes it again.
     *
     * @return void
     */
    public static function invalidate() {
        delete_transient( FacebookPluginConfig::FIELD_MAPPING_REVIEW_TRANSIENT );
    }

    /**
     * Clears the cached summary on every hook that may change it. Called
     * once the integrations are initialized.
     *
     * @param callable $integrations Returns the lead-form integrations that
     *                               support field mapping.
     * @return void
     */
    public static function register_invalidation_hooks( callable $integrations ) {
        $hooks = self::GENERIC_CHANGE_HOOKS;
        foreach ( call_user_func( $integrations ) as $integration ) {
            $hooks = array_merge( $hooks, $integration->get_form_change_hooks() );
        }
        foreach ( array_unique( $hooks ) as $hook ) {
            add_action( $hook, array( self::class, 'invalidate' ), 10, 0 );
        }
    }
}
