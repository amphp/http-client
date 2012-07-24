<?php

use Artax\Framework\Routing\BadResourceClassException;

/**
 * @covers Artax\Framework\Routing\BadResourceClassException
 */
class BadResourceClassExceptionTest extends PHPUnit_Framework_TestCase {
    
    public function testIsRuntimeException() {
        try {
            throw new BadResourceClassException;
        } catch (Exception $e) {
            $this->assertInstanceOf('RuntimeException', $e);
        }
    }
}
