<?php

class HttpExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\blocks\http\HttpException
   * @group  exceptions
   */
  public function testHttpExceptionIsArtaxException()
  {
    $e = new artax\blocks\http\HttpException();
    $this->assertInstanceOf('artax\exceptions\Exception', $e);
  }
}


