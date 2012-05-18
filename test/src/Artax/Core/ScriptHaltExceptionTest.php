<?php

class ScriptHaltExceptionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\Core\ScriptHaltException
     */
    public function testScriptHaltExceptionIsRuntimeException()
    {
        $e = new Artax\Core\ScriptHaltException;
        $this->assertInstanceOf('RuntimeException', $e);
    }
}
