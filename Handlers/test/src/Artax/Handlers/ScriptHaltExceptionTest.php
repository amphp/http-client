<?php

class ScriptHaltExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Exceptions\ScriptHaltException
   * @group  exceptions
   */
  public function testScriptHaltExceptionIsRuntimeException()
  {
    $e = new Artax\Exceptions\ScriptHaltException();
    $this->assertInstanceOf('RuntimeException', $e);
  }
}
