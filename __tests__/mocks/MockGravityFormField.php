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

final class MockGravityFormField {
  public $type;
  public $id;
  public $inputs = array();

  public function __construct($type, $id) {
    $this->type = $type;
    $this->id = $id;
  }

  public function addLabel($label, $id) {
    $input = array('label' => $label, 'id' => $id);
    $this->inputs[] = $input;
  }
}
