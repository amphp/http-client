<?php

class ScriptHaltExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Handlers\ScriptHaltException
   */
  public function testScriptHaltExceptionIsRuntimeException()
  {
    $e = new Artax\Handlers\ScriptHaltException();
    $this->assertInstanceOf('RuntimeException', $e);
  }
}
