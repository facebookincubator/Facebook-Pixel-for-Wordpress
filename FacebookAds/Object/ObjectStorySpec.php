<?php
 /*
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 * All rights reserved.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace FacebookPixelPlugin\FacebookAds\Object;

use FacebookPixelPlugin\FacebookAds\Object\Fields\AdCreativeObjectStorySpecFields;
use FacebookPixelPlugin\FacebookAds\Object\Traits\FieldValidation;

/**
 * @deprecated use AdCreativeObjectStorySpec instead
 */
class ObjectStorySpec extends AdCreativeObjectStorySpec {
  use FieldValidation;

  /**
   * @return AdCreativeObjectStorySpecFields
   */
  public static function getFieldsEnum() {
    return AdCreativeObjectStorySpecFields::getInstance();
  }
}
