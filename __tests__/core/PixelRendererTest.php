<?php
/*
 * Copyright (C) 2017-present, Facebook, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\PixelRenderer;
use FacebookPixelPlugin\Core\ServerEventHelper;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\CustomData;


/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * All tests in this test class should be run in separate PHP process to
 * make sure tests are isolated.
 * Stop preserving global state from the parent process.
 */
final class PixelRendererTest extends FacebookWordpressTestBase {
  public function testPixelRenderForStandardEvent() {
    $event = (new Event())->setEventName('Lead');
    $code = PixelRenderer::render($event);

    $expected = "<script type='text/javascript'>
  fbq('track', 'Lead', {});
</script>";

    $this->assertEquals($expected, $code);
  }

  public function testPixelRenderForCustomEvent() {
    $event = (new Event())->setEventName('Custom');
    $code = PixelRenderer::render($event);

    $expected = "<script type='text/javascript'>
  fbq('trackCustom', 'Custom', {});
</script>";

    $this->assertEquals($expected, $code);
  }

  public function testPixelRenderForCustomData() {
    $custom_data = (new CustomData())
                    ->setCurrency('USD')
                    ->setValue('30.00');

    $event = (new Event())
              ->setEventName('Purchase')
              ->setCustomData($custom_data);

    $code = PixelRenderer::render($event);

    $expected = "<script type='text/javascript'>
  fbq('track', 'Purchase', {\"value\":\"30.00\",\"currency\":\"USD\"});
</script>";

    $this->assertEquals($expected, $code);
  }
}
