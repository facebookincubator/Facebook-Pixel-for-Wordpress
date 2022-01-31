<?php
/*
 * Copyright (C) 2017-present, Meta, Inc.
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
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
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
    FacebookWordpressOptions::setVersionInfo();
    $agent_string = FacebookWordpressOptions::getAgentString();

    $event = (new Event())
              ->setEventName('Lead')
              ->setEventId('TestEventId');
    $code = PixelRenderer::render(array($event), 'Test');

    $expected = sprintf("<script type='text/javascript'>
  fbq('set', 'agent', '%s', '');
  fbq('track', 'Lead', {
    \"fb_integration_tracking\": \"Test\"
}, {
    \"eventID\": \"TestEventId\"
});
</script>", $agent_string);

    $this->assertEquals($expected, $code);
  }

  public function testPixelRenderForCustomEvent() {
    FacebookWordpressOptions::setVersionInfo();
    $agent_string = FacebookWordpressOptions::getAgentString();

    $event = (new Event())
              ->setEventName('Custom')
              ->setEventId('TestEventId');

    $code = PixelRenderer::render(array($event), 'Test');

    $expected = sprintf("<script type='text/javascript'>
  fbq('set', 'agent', '%s', '');
  fbq('trackCustom', 'Custom', {
    \"fb_integration_tracking\": \"Test\"
}, {
    \"eventID\": \"TestEventId\"
});
</script>", $agent_string);

    $this->assertEquals($expected, $code);
  }

  public function testPixelRenderForCustomData() {
    FacebookWordpressOptions::setVersionInfo();
    $agent_string = FacebookWordpressOptions::getAgentString();

    $custom_data = (new CustomData())
                    ->setCurrency('USD')
                    ->setValue('30.00');

    $event = (new Event())
              ->setEventName('Purchase')
              ->setEventId('TestEventId')
              ->setCustomData($custom_data);

    $code = PixelRenderer::render(array($event), 'Test');

    $expected = sprintf("<script type='text/javascript'>
  fbq('set', 'agent', '%s', '');
  fbq('track', 'Purchase', {
    \"value\": \"30.00\",
    \"currency\": \"usd\",
    \"fb_integration_tracking\": \"Test\"
}, {
    \"eventID\": \"TestEventId\"
});
</script>", $agent_string);

    $this->assertEquals($expected, $code);
  }

  public function testPixelRenderForMultipleEvents() {
    FacebookWordpressOptions::setVersionInfo();
    $agent_string = FacebookWordpressOptions::getAgentString();

    $event1 = (new Event())
              ->setEventName('Lead')
              ->setEventId('TestEventId1');
    $event2 = (new Event())
              ->setEventName('Lead')
              ->setEventId('TestEventId2');

    $code = PixelRenderer::render(array($event1, $event2), 'Test');

    $expected = sprintf("<script type='text/javascript'>
  fbq('set', 'agent', '%s', '');
  fbq('track', 'Lead', {
    \"fb_integration_tracking\": \"Test\"
}, {
    \"eventID\": \"TestEventId1\"
});

  fbq('track', 'Lead', {
    \"fb_integration_tracking\": \"Test\"
}, {
    \"eventID\": \"TestEventId2\"
});
</script>", $agent_string);

    $this->assertEquals($expected, $code);
  }
}
