<?php

class PcntlInterruptExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Core\Handlers\PcntlInterruptException
   */
  public function testPcntlInterruptExceptionIsRuntimeException()
  {
    $e = new Artax\Core\Handlers\PcntlInterruptException;
    $this->assertInstanceOf('RuntimeException', $e);
  }
}
