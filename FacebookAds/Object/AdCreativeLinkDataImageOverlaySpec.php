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
use FacebookPixelPlugin\FacebookAds\Http\RequestInterface;
use FacebookPixelPlugin\FacebookAdsTypeChecker;
use FacebookPixelPlugin\FacebookAds\Object\Fields\AdCreativeLinkDataImageOverlaySpecFields;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdCreativeLinkDataImageOverlaySpecCustomTextTypeValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdCreativeLinkDataImageOverlaySpecOverlayTemplateValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdCreativeLinkDataImageOverlaySpecPositionValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdCreativeLinkDataImageOverlaySpecTextFontValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdCreativeLinkDataImageOverlaySpecTextTypeValues;
use FacebookPixelPlugin\FacebookAds\Object\Values\AdCreativeLinkDataImageOverlaySpecThemeColorValues;

/**
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class AdCreativeLinkDataImageOverlaySpec extends AbstractObject {

  /**
   * @return AdCreativeLinkDataImageOverlaySpecFields
   */
  public static function getFieldsEnum() {
    return AdCreativeLinkDataImageOverlaySpecFields::getInstance();
  }

  protected static function getReferencedEnums() {
    $ref_enums = array();
    $ref_enums['CustomTextType'] = AdCreativeLinkDataImageOverlaySpecCustomTextTypeValues::getInstance()->getValues();
    $ref_enums['OverlayTemplate'] = AdCreativeLinkDataImageOverlaySpecOverlayTemplateValues::getInstance()->getValues();
    $ref_enums['Position'] = AdCreativeLinkDataImageOverlaySpecPositionValues::getInstance()->getValues();
    $ref_enums['TextFont'] = AdCreativeLinkDataImageOverlaySpecTextFontValues::getInstance()->getValues();
    $ref_enums['TextType'] = AdCreativeLinkDataImageOverlaySpecTextTypeValues::getInstance()->getValues();
    $ref_enums['ThemeColor'] = AdCreativeLinkDataImageOverlaySpecThemeColorValues::getInstance()->getValues();
    return $ref_enums;
  }


}
