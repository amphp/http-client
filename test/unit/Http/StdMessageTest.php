<?php

use Artax\Http\MutableStdResponse;

class StdMessageTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\StdMessage::getHttpVersion
     */
    public function testHttpVersionAccessors() {
        $response = new MutableStdResponse;
        $this->assertEquals('1.1', $response->getHttpVersion());
        $this->assertNull($response->setHttpVersion('1.0'));
        $this->assertEquals('1.0', $response->getHttpVersion());
    }
    
    /**
     * @covers Artax\Http\StdMessage::getBody
     */
    public function testBodyAccessors() {
        $response = new MutableStdResponse;
        $this->assertEquals('', $response->getBody());
        $this->assertNull($response->setBody('entity body'));
        $this->assertEquals('entity body', $response->getBody());
    }
    
    /**
     * @covers Artax\Http\StdMessage::getHeader
     * @expectedException RuntimeException
     */
    public function testHeaderGetterThrowsExceptionOnInvalidHeaderRequest() {
        $response = new MutableStdResponse;
        $response->getHeader('Doesnt-Exist');
    }
    
    /**
     * @covers Artax\Http\StdMessage::getHeader
     */
    public function testHeaderAccessors() {
        $response = new MutableStdResponse;
        $this->assertNull($response->setHeader('Content-Type', 'text/html'));
        $this->assertEquals('text/html', $response->getHeader('Content-Type'));
        
        $this->assertNull($response->setHeader('content-type', 'application/json'));
        $this->assertEquals('application/json', $response->getHeader('CONTENT-TYPE'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::hasHeader
     */
    public function testHasHeaderReturnsBoolOnHeaderExistence() {
        $response = new MutableStdResponse;
        $this->assertFalse($response->hasHeader('Content-Type'));
        $response->setHeader('Content-Type', 'text/html');
        $this->assertTrue($response->hasHeader('Content-TYPE'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::getAllHeaders
     */
    public function testGetAllHeadersReturnsHeaderStorageArray() {
        $response = new MutableStdResponse;
        $response->setHeader('Content-Type', 'text/html');
        $response->setHeader('Content-Length', 42);
        
        $expected = array(
            'CONTENT-TYPE' => 'text/html',
            'CONTENT-LENGTH' => 42
        );
        
        $this->assertEquals($expected, $response->getAllHeaders());
    }
}
