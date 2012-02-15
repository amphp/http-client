<?php

class LogicExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\exceptions\LogicException
   * @covers artax\exceptions\Exception
   * @group  exceptions
   */
  public function testLogicExceptionIsArtaxExceptionAndExtendsSplException()
  {
    $e = new artax\exceptions\LogicException();
    $this->assertInstanceOf('LogicException', $e);
    $this->assertInstanceOf('artax\exceptions\Exception', $e);
  }
}


