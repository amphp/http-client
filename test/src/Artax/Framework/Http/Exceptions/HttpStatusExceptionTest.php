<?php

use Artax\Framework\Http\Exceptions\HttpStatusException;

class HttpStatusExceptionTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Http\Exceptions\HttpStatusException::__construct
     */
    public function testHttpStatusExceptionExtendsRuntimeException() {
        try {
            throw new HttpStatusException('Not Found', 404);
        } catch (HttpStatusException $e) {
            $this->assertInstanceOf('RuntimeException', $e);
        }
    }
    
    /**
     * @covers Artax\Framework\Http\Exceptions\HttpStatusException::__construct
     * @expectedException DomainException
     */
    public function testConstructorThrowsDomainExceptionOnInvalidStatusCode() {
        $e = new HttpStatusException('Not Found', 99);
    }
}
