<?php

class BadFunctionCallExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\exceptions\BadFunctionCallException
   * @covers artax\exceptions\Exception
   * @group  exceptions
   */
  public function testBadFunctionCallExceptionIsArtaxExceptionAndExtendsSplException()
  {
    $e = new artax\exceptions\BadFunctionCallException();
    $this->assertInstanceOf('BadFunctionCallException', $e);
    $this->assertInstanceOf('artax\exceptions\Exception', $e);
  }
}


