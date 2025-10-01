<?php
 /*
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 * All rights reserved.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace FacebookPixelPlugin\FacebookAds\Object\CustomAudienceNormalizers;

use FacebookPixelPlugin\FacebookAdsObject\Fields\CustomAudienceMultikeySchemaFields;
use FacebookPixelPlugin\FacebookAdsObject\CustomAudienceNormalizers\ValueNormalizerInterface;

class PhoneNormalizer implements ValueNormalizerInterface {

  /**
   * @param string $key
   * @param string $key_value
   * @return boolean
   */
  public function shouldNormalize($key, $key_value) {
    return $key === CustomAudienceMultikeySchemaFields::PHONE;
  }

  /**
   * @param string $key
   * @param string $key_value
   * @return string
   */
  public function normalize($key, $key_value) {
    return preg_replace('/[^0-9]/', '', trim($key_value));
  }
}
