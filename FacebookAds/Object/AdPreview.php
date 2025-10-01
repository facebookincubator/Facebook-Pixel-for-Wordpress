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
use FacebookPixelPlugin\FacebookAdsObject\Fields\AdPreviewFields;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdPreviewAdFormatValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdPreviewCreativeFeatureValues;
use FacebookPixelPlugin\FacebookAdsObject\Values\AdPreviewRenderTypeValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class AdPreview extends AbstractObject {

  /**
   * @deprecated getEndpoint function is deprecated
   */
  protected function getEndpoint() {
    return 'previews';
  }

  /**
   * @return AdPreviewFields
   */
  public static function getFieldsEnum() {
    return AdPreviewFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['AdFormat'] = AdPreviewAdFormatValues::getInstance()->getValues();
    $ref_enums['CreativeFeature'] = AdPreviewCreativeFeatureValues::getInstance()->getValues();
    $ref_enums['RenderType'] = AdPreviewRenderTypeValues::getInstance()->getValues();
    return $ref_enums;
  }


}
