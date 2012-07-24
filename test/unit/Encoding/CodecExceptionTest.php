<?php

use Artax\Encoding\CodecException;

require_once __DIR__ . '/BaseEncodingTest.php';

/**
 * @covers Artax\Encoding\CodecException
 */
class CodecExceptionTest extends BaseEncodingTest {
    
    public function testIsRuntimeException() {
        try {
            throw new CodecException;
        } catch (CodecException $e) {
            $this->assertInstanceOf('RuntimeException', $e);
        }
    }
}
