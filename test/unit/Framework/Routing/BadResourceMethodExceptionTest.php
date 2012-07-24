<?php

use Artax\Framework\Routing\BadResourceMethodException;

/**
 * @covers Artax\Framework\Routing\BadResourceMethodException
 */
class BadResourceMethodExceptionTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Routing\BadResourceMethodException::__construct
     */
    public function testIsRuntimeException() {
        try {
            throw new BadResourceMethodException(array('get', 'post'));
        } catch (Exception $e) {
            $this->assertInstanceOf('RuntimeException', $e);
            return $e;
        }
    }
    
    /**
     * @depends testIsRuntimeException
     * @covers Artax\Framework\Routing\BadResourceMethodException::getAvailableMethods
     */
    public function testGetAvailableMethodsReturnsArrayOfMethods($e) {
        $this->assertEquals(array('get', 'post'), $e->getAvailableMethods());
    }
}
