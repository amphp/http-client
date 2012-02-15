<?php

class RuntimeExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\exceptions\RuntimeException
   * @covers artax\exceptions\Exception
   * @group  exceptions
   */
  public function testRuntimeExceptionIsArtaxExceptionAndExtendsSplException()
  {
    $e = new artax\exceptions\RuntimeException();
    $this->assertInstanceOf('RuntimeException', $e);
    $this->assertInstanceOf('artax\exceptions\Exception', $e);
  }
}


