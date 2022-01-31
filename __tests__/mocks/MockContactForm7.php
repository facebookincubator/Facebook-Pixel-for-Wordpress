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

final class MockContactForm7 {
  private $form_tags = [];
  private $throw = false;

  public function set_throw($throw) {
    $this->throw = $throw;
  }

  public function add_tag($basetype, $name, $value) {
    $tag = new MockContactForm7Tag($basetype, $name);
    $_POST[$name] = $value;

    $this->form_tags[] = $tag;
  }

  public function scan_form_tags() {
    if ($this->throw) {
      throw new \Exception("Error scanning form tags!");
    }

    return $this->form_tags;
  }
}
