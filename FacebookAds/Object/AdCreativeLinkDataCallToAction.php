<?php
 /*
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 * All rights reserved.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace FacebookPixelPlugin\FacebookAds\Object;

use FacebookPixelPlugin\FacebookAds\ApiRequest;
use FacebookPixelPlugin\FacebookAds\Cursor;
use FacebookPixelPlugin\FacebookAds\Http\RequestInterface;
use FacebookPixelPlugin\FacebookAds\TypeChecker;
use FacebookPixelPlugin\FacebookAds\Object\Fields\AdCreativeLinkDataCallToActionFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdCreativeLinkDataCallToActionTypeValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class AdCreativeLinkDataCallToAction extends AbstractObject {

  /**
   * @return AdCreativeLinkDataCallToActionFields
   */
  public static function getFieldsEnum() {
    return AdCreativeLinkDataCallToActionFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['Type'] = AdCreativeLinkDataCallToActionTypeValues::getInstance()->getValues();
    return $ref_enums;
  }


}
