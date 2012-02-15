<?php

class InvalidArgumentExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\exceptions\InvalidArgumentException
   * @covers artax\exceptions\Exception
   * @group  exceptions
   */
  public function testInvalidArgumentExceptionIsArtaxExceptionAndExtendsSplException()
  {
    $e = new artax\exceptions\InvalidArgumentException();
    $this->assertInstanceOf('InvalidArgumentException', $e);
    $this->assertInstanceOf('artax\exceptions\Exception', $e);
  }
}


