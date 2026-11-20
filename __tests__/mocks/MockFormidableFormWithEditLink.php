<?php
/**
 * Facebook Pixel Plugin MockFormidableFormWithEditLink class.
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

namespace FacebookPixelPlugin\Tests\Mocks;

/**
 * Stands in for Formidable's FrmForm (aliased to it in a test) with its
 * get_edit_link() helper, recording the ids it was called with.
 */
final class MockFormidableFormWithEditLink {
    /**
     * The form ids get_edit_link() was called with.
     *
     * @var array
     */
    public static $calls = array();

    /**
     * Formidable's edit link helper.
     *
     * @param int $form_id The form id.
     * @return string
     */
    public static function get_edit_link( $form_id ) {
        self::$calls[] = $form_id;
        return 'https://example.com/wp-admin/admin.php?page=formidable&frm_action=edit&id=' . $form_id . '&from=helper';
    }
}
