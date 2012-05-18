<?php

class FatalErrorExceptionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\Core\FatalErrorException
     */
    public function testFatalErrorExceptionIsRuntimeException()
    {
        $e = new Artax\Core\FatalErrorException();
        $this->assertInstanceOf('RuntimeException', $e);
    }
}
