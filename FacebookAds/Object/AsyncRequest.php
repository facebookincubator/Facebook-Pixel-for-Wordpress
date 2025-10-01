<?php
 /*
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 * All rights reserved.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace FacebookPixelPlugin\FacebookAds\Object;

use FacebookPixelPlugin\FacebookAdsApiRequest;
use FacebookPixelPlugin\FacebookAdsCursor;
use FacebookPixelPlugin\FacebookAdsHttp\RequestInterface;
use FacebookPixelPlugin\FacebookAdsTypeChecker;
use FacebookPixelPlugin\FacebookAdsObject\Fields\AsyncRequestFields;
use FacebookPixelPlugin\FacebookAdsObject\Values\AsyncRequestStatusValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AsyncRequestTypeValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class AsyncRequest extends AbstractCrudObject {

  /**
   * @return AsyncRequestFields
   */
  public static function getFieldsEnum() {
    return AsyncRequestFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['Status'] = AsyncRequestStatusValues::getInstance()->getValues();
    $ref_enums['Type'] = AsyncRequestTypeValues::getInstance()->getValues();
    return $ref_enums;
  }


}
