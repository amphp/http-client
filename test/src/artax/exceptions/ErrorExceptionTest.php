<?php

class ErrorExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\exceptions\ErrorException
   * @covers artax\exceptions\Exception
   * @group  exceptions
   */
  public function testErrorExceptionIsArtaxExceptionAndExtendsSplException()
  {
    $e = new artax\exceptions\ErrorException();
    $this->assertInstanceOf('ErrorException', $e);
    $this->assertInstanceOf('artax\exceptions\Exception', $e);
  }
}


