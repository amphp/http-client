<?php

class ScriptHaltExceptionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\ScriptHaltException
     */
    public function testScriptHaltExceptionIsRuntimeException()
    {
        $e = new Artax\ScriptHaltException;
        $this->assertInstanceOf('RuntimeException', $e);
    }
}
