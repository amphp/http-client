<?php

class HttpExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\http\HttpException
   * @group  exceptions
   */
  public function testHttpExceptionIsArtaxException()
  {
    $e = new artax\http\HttpException();
    $this->assertInstanceOf('artax\exceptions\Exception', $e);
  }
}


