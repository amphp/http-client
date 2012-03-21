<?php

class PcntlInterruptExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Handlers\PcntlInterruptException
   */
  public function testPcntlInterruptExceptionIsRuntimeException()
  {
    $e = new Artax\Handlers\PcntlInterruptException;
    $this->assertInstanceOf('RuntimeException', $e);
  }
}
