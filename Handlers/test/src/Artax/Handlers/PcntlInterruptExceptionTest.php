<?php

class PcntlInterruptExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Exceptions\PcntlInterruptException
   * @group  exceptions
   */
  public function testPcntlInterruptExceptionIsRuntimeException()
  {
    $e = new Artax\Exceptions\PcntlInterruptException;
    $this->assertInstanceOf('RuntimeException', $e);
  }
}
