<?php

class FatalErrorExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Core\Handlers\FatalErrorException
   */
  public function testFatalErrorExceptionIsRuntimeException()
  {
    $e = new Artax\Core\Handlers\FatalErrorException();
    $this->assertInstanceOf('RuntimeException', $e);
  }
}
