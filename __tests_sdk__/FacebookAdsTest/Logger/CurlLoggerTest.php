<?php
/**
 * Copyright (c) 2014-present, Facebook, Inc. All rights reserved.
 *
 * You are hereby granted a non-exclusive, worldwide, royalty-free license to
 * use, copy, modify, and distribute this software in source code or binary
 * form for use in connection with the web services and APIs provided by
 * Facebook.
 *
 * As with any software that integrates with the Facebook platform, your use
 * of this software is subject to the Facebook Developer Principles and
 * Policies [http://developers.facebook.com/policy/]. This copyright notice
 * shall be included in all copies or substantial portions of the software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 */

namespace FacebookPixelPlugin\FacebookAdsTest\Logger;

use FacebookPixelPlugin\FacebookAds\Http\RequestInterface;
use FacebookPixelPlugin\FacebookAds\Logger\CurlLogger;
use FacebookPixelPlugin\FacebookAds\Logger\CurlLogger\JsonAwareParameters;

class CurlLoggerTest extends AbstractLoggerTest {

  /**
   * @var resource
   */
  protected $handle;

  public function setup(): void {
    $this->handle = fopen('php://temp', 'w+');
  }

  public function tearDown(): void {
    fclose($this->handle);
  }

  /**
   * @return resource
   */
  protected function getHandle() {
    return $this->handle;
  }

  /**
   * @return string
   */
  protected function getBuffer() {
    rewind($this->getHandle());

    return stream_get_contents($this->getHandle());
  }

  /**
   * @return CurlLogger
   */
  protected function createLogger() {
    return new CurlLogger($this->getHandle());
  }

  protected function createRequestMock() {
    $query = $this->createParametersMock();
    $query->method('export')->willReturn(array(
      'appsecret_proof' => '<APPSECRET_PROOF>',
      'access_token' => '<ACCESS_TOKEN>',
      'query_field' => 'query_value',
    ));

    $body = $this->createParametersMock();
    $body->method('export')->willReturn(array(
      'body_field' => 'body_value',
    ));

    $files = $this->createParametersMock();
    $files->method('export')->willReturn(array(
      'file_field' => 'filepath',
    ));

    $request = parent::createRequestMock();
    $request->method('getQueryParams')->willReturn($query);
    $request->method('getBodyParams')->willReturn($body);
    $request->method('getFileParams')->willReturn($files);

    return $request;
  }

  public function testLog() {
    $logger = $this->createLogger();

    $this->assertNull($logger->log(
      static::VALUE_LOG_LEVEL, static::VALUE_LOG_MESSAGE));
    $this->assertSame('', $this->getBuffer());
  }

  /**
   * @return array
   */
  public function logRequestProvider() {
    return array(
      array(RequestInterface::METHOD_GET),
      array(RequestInterface::METHOD_POST),
      array(RequestInterface::METHOD_PUT),
      array(RequestInterface::METHOD_DELETE),
    );
  }

  /**
   * @dataProvider logRequestProvider
   * @param string $http_method
   */
  public function testLogRequest($http_method) {
    $request = $this->createRequestMock();
    $request->method('getMethod')->willReturn($http_method);

    $logger = $this->createLogger();
    $this->assertNull($logger->logRequest(static::VALUE_LOG_LEVEL, $request));

    $output = $this->getBuffer();
    $method_flag = CurlLogger::getMethodFlag($http_method);

    $this->assertStringStartsWith(
      'curl'.($method_flag ? ' -'.$method_flag : ''),
      $output);
    $this->assertStringContainsString("'query_field=query_value'", $output);
    $this->assertStringContainsString("'body_field=body_value'", $output);
    $this->assertStringContainsString("'file_field=filepath'", $output);
    $this->assertStringContainsString('/v', $output);
  }

  public function testLogResponse() {
    $logger = $this->createLogger();

    $this->assertNull($logger->logResponse(
      static::VALUE_LOG_LEVEL, $this->createResponseMock()));
    $this->assertSame('', $this->getBuffer());
  }

  public function testJsonPrettyPrint() {
    $logger = $this->createLogger();
    $this->assertFalse($logger->isJsonPrettyPrint());
    $logger->setJsonPrettyPrint(true);
    $this->assertTrue($logger->isJsonPrettyPrint());

    $query = new JsonAwareParameters(array(
      'json_field' => array_fill(0, 3, 'json_value'),
    ));
    $body = $files = $this->createParametersMock();

    $request = parent::createRequestMock();
    $request->method('getQueryParams')->willReturn($query);
    $request->method('getBodyParams')->willReturn($body);
    $request->method('getFileParams')->willReturn($files);

    $logger->logRequest(static::VALUE_LOG_LEVEL, $request);

    $logger->setJsonPrettyPrint(false);
    $this->assertFalse($logger->isJsonPrettyPrint());
  }
}
