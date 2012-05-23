<?php

class FatalErrorExceptionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\FatalErrorException
     */
    public function testFatalErrorExceptionIsRuntimeException()
    {
        $e = new Artax\FatalErrorException();
        $this->assertInstanceOf('RuntimeException', $e);
    }
}
