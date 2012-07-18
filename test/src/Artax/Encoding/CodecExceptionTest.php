<?php

use Artax\Encoding\CodecException;

/**
 * @covers Artax\Encoding\CodecException
 */
class CodecExceptionTest extends PHPUnit_Framework_TestCase {
    
    public function testIsRuntimeException() {
        try {
            throw new CodecException;
        } catch (CodecException $e) {
            $this->assertInstanceOf('RuntimeException', $e);
        }
    }
}
