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

namespace FacebookPixelPlugin\Tests;

use FacebookPixelPlugin\Core\FacebookPixel;

final class FacebookPixelTest extends FacebookWordpressTestBase {
  public function testCanGetAndSetPixelId() {
    FacebookPixel::initialize('123');
    $this->assertEquals('123', FacebookPixel::getPIxelId());
    FacebookPixel::setPixelId('1');
    $this->assertEquals('1', FacebookPixel::getPIxelId());
  }

  public function testCanGetPixelInitCode() {
    FacebookPixel::setPixelId('');
    $code = FacebookPixel::getPixelInitCode('mockAgent', array('key' => 'value'));
    $this->assertEmpty($code);

    FacebookPixel::setPixelId('123');
    $code = FacebookPixel::getPixelInitCode('mockAgent', array('key' => 'value'));
    $this->assertStringStartsWith('<script', $code);
    $this->assertStringEndsWith('</script>', $code);
    $this->assertTrue(\strpos($code, '123') !== false);
    $this->assertTrue(\strpos($code, 'init') !== false);
    $this->assertTrue(\strpos($code, '"key": "value"') !== false);
    $this->assertTrue(\strpos($code, '"agent": "mockAgent"') !== false);

    $code = FacebookPixel::getPixelInitCode('mockAgent', '{"key": "value"}', false);
    $this->assertStringStartsNotWith('<script', $code);
    $this->assertStringEndsNotWith('</script>', $code);
    $this->assertTrue(\strpos($code, '123') !== false);
    $this->assertTrue(\strpos($code, 'init') !== false);
    $this->assertTrue(\strpos($code, '{"key": "value"}') !== false);
    $this->assertTrue(\strpos($code, '"agent": "mockAgent"') !== false);
  }

  public function testCanGetPixelTrackCode() {
    FacebookPixel::setPixelId('');
    $code = FacebookPixel::getPixelTrackCode('mockEvent', array('key' => 'value'));
    $this->assertEmpty($code);

    FacebookPixel::setPixelId('123');
    $code = FacebookPixel::getPixelTrackCode('mockEvent', array('key' => 'value'));
    $this->assertStringStartsWith('<script', $code);
    $this->assertStringEndsWith('</script>', $code);
    $this->assertTrue(\strpos($code, 'track') !== false);
    $this->assertTrue(\strpos($code, '"key": "value"') !== false);
    $this->assertTrue(\strpos($code, 'mockEvent') !== false);

    $code = FacebookPixel::getPixelTrackCode('mockEvent', '{"key": "value"}', false , false);
    $this->assertStringStartsNotWith('<script', $code);
    $this->assertStringEndsNotWith('</script>', $code);
    $this->assertTrue(\strpos($code, 'trackCustom') !== false);
    $this->assertTrue(\strpos($code, '{"key": "value"}') !== false);
    $this->assertTrue(\strpos($code, 'mockEvent') !== false);
  }

  public function testCanGetPixelNoScriptCode() {
    FacebookPixel::setPixelId('');
    $code = FacebookPixel::getPixelNoscriptCode('mockEvent', array('key' => 'value'));
    $this->assertEmpty($code);

    FacebookPixel::setPixelId('123');
    $code = FacebookPixel::getPixelNoscriptCode('mockEvent', array('key' => 'value'));
    $this->assertTrue(\strpos($code, '123') !== false);
    $this->assertTrue(\strpos($code, 'mockEvent') !== false);
    $this->assertTrue(\strpos($code, '[key]=value') !== false);
  }
}
