<?php
 /*
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 * All rights reserved.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace FacebookPixelPlugin\FacebookAds\Object\Fields;

use FacebookPixelPlugin\FacebookAdsEnum\AbstractEnum;

/**
 * @method static ClickTrackingTagFields getInstance()
 */
class ClickTrackingTagFields extends AbstractEnum {

  const ID = 'id';
  const ADD_TEMPLATE_PARAM = 'add_template_param';
  const URL = 'url';
  const ADGROUP_ID = 'adgroup_id';
}
