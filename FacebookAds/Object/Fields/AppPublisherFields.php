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

class AppPublisherFields extends AbstractEnum {

  const CONTENT_ID = 'content_id';
  const ICON_URL = 'icon_url';
  const ID = 'id';
  const NAME = 'name';
  const PLATFORM = 'platform';
  const STORE_NAME = 'store_name';
  const STORE_URL = 'store_url';

  public function getFieldTypes() {
    return array(
      'content_id' => 'string',
      'icon_url' => 'string',
      'id' => 'string',
      'name' => 'string',
      'platform' => 'string',
      'store_name' => 'string',
      'store_url' => 'string',
    );
  }
}
