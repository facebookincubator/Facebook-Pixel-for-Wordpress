<?php
/**
 * Facebook Pixel Plugin MockContactForm7 class.
 *
 * This file contains the main logic for MockContactForm7.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define MockContactForm7 class.
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

namespace FacebookPixelPlugin\Tests\Mocks;

/**
 * MockContactForm7 class.
 */
final class MockContactForm7 {
	/**
	 * An array of form tags.
	 *
	 * @var array
	 */
	private $form_tags = array();
	/**
	 * Whether to throw an exception when get_current() is called.
	 *
	 * @var bool
	 */
	private $throw = false;

	/**
	 * Sets whether to throw an exception when get_current() is called.
	 *
	 * @param bool $status Whether to throw an exception.
	 */
	public function set_throw( $status ) {
		$this->throw = $status;
	}

	/**
	 * Adds a form tag to the mock plugin.
	 *
	 * @param string $basetype The base type of the tag.
	 * @param string $name     The name of the tag.
	 * @param mixed  $value    The value of the tag.
	 */
	public function add_tag( $basetype, $name, $value ) {
		$tag            = new MockContactForm7Tag( $basetype, $name );
		$_POST[ $name ] = $value;

		$this->form_tags[] = $tag;
	}

	/**
	 * Scans and retrieves the form tags.
	 *
	 * If the 'throw' property is set to true, an exception is thrown.
	 *
	 * @throws \Exception If an error occurs during form tag scanning.
	 *
	 * @return array The array of form tags.
	 */
	public function scan_form_tags() {
		if ( $this->throw ) {
			throw new \Exception( 'Error scanning form tags!' );
		}

		return $this->form_tags;
	}
}
