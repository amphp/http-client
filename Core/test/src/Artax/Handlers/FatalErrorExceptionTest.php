<?php

class FatalErrorExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Handlers\FatalErrorException
   */
  public function testFatalErrorExceptionIsRuntimeException()
  {
    $e = new Artax\Handlers\FatalErrorException();
    $this->assertInstanceOf('RuntimeException', $e);
  }
}
