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

class ColumnSuggestionsFields extends AbstractEnum {

  const EXPLANATIONS = 'explanations';
  const FORMAT = 'format';
  const OBJECTIVE = 'objective';
  const OPTIMIZATION_GOALS = 'optimization_goals';

  public function getFieldTypes() {
    return array(
      'explanations' => 'Object',
      'format' => 'list<string>',
      'objective' => 'list<string>',
      'optimization_goals' => 'list<string>',
    );
  }
}
