<?php
 /*
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 * All rights reserved.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace FacebookPixelPlugin\FacebookAds\Object\Values;

use FacebookPixelPlugin\FacebookAdsEnum\AbstractEnum;

/**
 * @method static PageRoles getInstance()
 */
class PageRoles extends AbstractEnum {

  const ADVERTISER  = 'ADVERTISER';
  const CONTENT_CREATOR = 'CONTENT_CREATOR';
  const MANAGER = 'MANAGER';
  const MODERATOR = 'MODERATOR';
  const INSIGHTS_ANALYST = 'INSIGHTS_ANALYST';
}
