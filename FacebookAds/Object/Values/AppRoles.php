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
 * @method static AppRoles getInstance()
 */
class AppRoles extends AbstractEnum {

  const ADMINISTRATOR  = 'ADMINISTRATOR';
  const DEVELOPER = 'DEVELOPER';
  const TESTER = 'TESTER';
  const INSIGHTS_USER = 'INSIGHTS_USER';
  const TEST_USER = 'TEST_USER';

}
