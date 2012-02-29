<?php

class ScriptHaltExceptionTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\exceptions\ScriptHaltException
   * @group  exceptions
   */
  public function testScriptHaltExceptionIsArtaxException()
  {
    $e = new artax\exceptions\ScriptHaltException();
    $this->assertInstanceOf('artax\exceptions\Exception', $e);
  }
}
