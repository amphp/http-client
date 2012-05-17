<?php

class ScriptHaltExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Core\Handlers\ScriptHaltException
   */
  public function testScriptHaltExceptionIsRuntimeException()
  {
    $e = new Artax\Core\Handlers\ScriptHaltException();
    $this->assertInstanceOf('RuntimeException', $e);
  }
}
