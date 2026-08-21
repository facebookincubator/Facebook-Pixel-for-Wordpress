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

use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;

/**
 * Coverage for the form-field -> lead-parameter mapping path shared by the
 * TrackableLeadFormIntegrationBase subclasses.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class TrackableLeadFormMappingTest extends FacebookWordpressTestBase {

    /**
     * When a form field is mapped to a composite Lead parameter (e.g. an
     * address), apply_field_mapping() should expand it into its sub-parameters
     * (city/state/zip/country) rather than assigning the raw composite value.
     *
     * This is not implemented yet: apply_field_mapping() currently does a flat
     * assignment, and composite targets aren't in VALID_LEAD_PARAMETERS. It is
     * blocked on the mapping UI, which will define how composite fields declare
     * their sub-field targets. See the TODO(mapping-UI) note in
     * TrackableLeadFormIntegrationBase::apply_field_mapping().
     *
     * Intended assertions once implemented: mapping an 'address' form field
     * should yield lead data with 'city', 'state', 'zip', and 'country' keys.
     *
     * @return void
     */
    public function testCompositeAddressMappingIsExpanded() {
        $this->markTestIncomplete(
            'Composite (address) field mapping breakdown is pending the mapping '
            . 'UI. See TrackableLeadFormIntegrationBase::apply_field_mapping '
            . 'TODO(mapping-UI).'
        );
    }
}
