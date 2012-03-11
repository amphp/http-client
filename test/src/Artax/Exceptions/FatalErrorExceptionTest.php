<?php

class FatalErrorExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Exceptions\FatalErrorException
   * @group  exceptions
   */
  public function testFatalErrorExceptionIsRuntimeException()
  {
    $e = new Artax\Exceptions\FatalErrorException();
    $this->assertInstanceOf('RuntimeException', $e);
  }
}
