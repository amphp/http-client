<?php

use Artax\Http\Negotiation\NotAcceptableException;

/**
 * @covers Artax\Http\Negotiation\NotAcceptableException
 */
class NotAcceptableExceptionTest extends PHPUnit_Framework_TestCase {
    
    public function testIsRuntimeException() {
        try {
            throw new NotAcceptableException('test');
        } catch (Exception $e) {
            $this->assertInstanceOf('RuntimeException', $e);
        }
    }
}
