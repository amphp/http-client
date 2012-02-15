<?php

class OutOfBoundsExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\exceptions\OutOfBoundsException
   * @covers artax\exceptions\Exception
   * @group  exceptions
   */
  public function testOutOfBoundsExceptionIsArtaxExceptionAndExtendsSplException()
  {
    $e = new artax\exceptions\OutOfBoundsException();
    $this->assertInstanceOf('OutOfBoundsException', $e);
    $this->assertInstanceOf('artax\exceptions\Exception', $e);
  }
}


