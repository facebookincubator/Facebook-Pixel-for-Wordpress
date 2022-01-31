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

namespace FacebookPixelPlugin\Tests\Mocks;

final class MockFormidableFormFieldValue {
  private $field;
  private $saved_value;

  public function __construct($field, $saved_value) {
    $this->field = $field;
    $this->saved_value = $saved_value;
  }

  public function get_field() {
    return $this->field;
  }

  public function get_saved_value() {
    return $this->saved_value;
  }
}
