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
use FacebookPixelPlugin\FacebookAdsObject\Fields\TargetingSentenceLineFields;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class TargetingSentenceLine extends AbstractCrudObject {

  /**
   * @deprecated getEndpoint function is deprecated
   */
  protected function getEndpoint() {
    return 'targetingsentencelines';
  }

  /**
   * @return TargetingSentenceLineFields
   */
  public static function getFieldsEnum() {
    return TargetingSentenceLineFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    return $ref_enums;
  }


}
