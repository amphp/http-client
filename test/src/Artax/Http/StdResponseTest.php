<?php

use Artax\Http\StdResponse;

class StdResponseTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\StdResponse::setHttpVersion
     * @covers Artax\Http\StdResponse::getHttpVersion
     */
    public function testHttpVersionAccessors() {
        $response = new StdResponse;
        $this->assertEquals('1.1', $response->getHttpVersion());
        $this->assertNull($response->setHttpVersion('1.0'));
        $this->assertEquals('1.0', $response->getHttpVersion());
    }
    
    /**
     * @covers Artax\Http\StdResponse::setStatusCode
     * @covers Artax\Http\StdResponse::getStatusCode
     */
    public function testStatusCodeAccessors() {
        $response = new StdResponse;
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($response->setStatusCode(404));
        $this->assertEquals(404, $response->getStatusCode());
    }
    
    /**
     * @covers Artax\Http\StdResponse::setStatusDescription
     * @covers Artax\Http\StdResponse::getStatusDescription
     */
    public function testStatusDescriptionAccessors() {
        $response = new StdResponse;
        $this->assertEquals('OK', $response->getStatusDescription());
        $this->assertNull($response->setStatusDescription('Not Found'));
        $this->assertEquals('Not Found', $response->getStatusDescription());
    }
    
    /**
     * @covers Artax\Http\StdResponse::setBody
     * @covers Artax\Http\StdResponse::getBody
     */
    public function testBodyAccessors() {
        $response = new StdResponse;
        $this->assertEquals('', $response->getBody());
        $this->assertNull($response->setBody('entity body'));
        $this->assertEquals('entity body', $response->getBody());
    }
    
}
