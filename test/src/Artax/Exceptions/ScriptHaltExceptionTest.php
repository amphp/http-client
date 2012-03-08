<?php

class ScriptHaltExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Exceptions\ScriptHaltException
   * @group  exceptions
   */
  public function testScriptHaltExceptionIsArtaxException()
  {
    $e = new Artax\Exceptions\ScriptHaltException();
    $this->assertInstanceOf('RuntimeException', $e);
  }
}
