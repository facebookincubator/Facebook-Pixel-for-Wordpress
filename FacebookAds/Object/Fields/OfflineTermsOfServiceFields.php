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
 * This class is auto-generated.
 *
 * For any issues or feature requests related to this class, please let us know
 * on github and we'll fix in our codegen framework. We'll not be able to accept
 * pull request for this class.
 *
 */

class OfflineTermsOfServiceFields extends AbstractEnum {

  const ACCEPT_TIME = 'accept_time';
  const ID = 'id';
  const SIGNED_BY_USER = 'signed_by_user';

  public function getFieldTypes() {
    return array(
      'accept_time' => 'int',
      'id' => 'string',
      'signed_by_user' => 'User',
    );
  }
}
