<?php

use Artax\Framework\Http\Exceptions\MethodNotAllowedException;

class MethodNotAllowedExceptionTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Http\Exceptions\MethodNotAllowedException::__construct
     */
    public function testMethodNotAllowedExceptionExtendsHttpStatusException() {
        try {
            $availableMethods = array('get', 'post');
            throw new MethodNotAllowedException($availableMethods);
        } catch (MethodNotAllowedException $e) {
            $this->assertInstanceOf('Artax\\Framework\\Http\\Exceptions\\HttpStatusException', $e);
        }
    }
    
    /**
     * @covers Artax\Framework\Http\Exceptions\MethodNotAllowedException::__construct
     * @covers Artax\Framework\Http\Exceptions\MethodNotAllowedException::getAvailableResourceMethods
     */
    public function testConstructorAssignsAvailableResourceMethodsProperty() {
        $availableMethods = array('get', 'post');
        $e = new MethodNotAllowedException($availableMethods);
        $this->assertEquals($availableMethods, $e->getAvailableResourceMethods());
    }
}
