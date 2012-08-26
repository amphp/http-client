<?php

use Artax\Http\StdResponse;

class StdMessageTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\StdMessage::getHttpVersion
     */
    public function testHttpVersionAccessors() {
        $response = new StdResponse;
        $this->assertEquals('1.1', $response->getHttpVersion());
        $this->assertNull($response->setHttpVersion('1.0'));
        $this->assertEquals('1.0', $response->getHttpVersion());
    }
    
    /**
     * @covers Artax\Http\StdMessage::getBody
     */
    public function testBodyAccessorReturnsStringEntityBodyIfAssigned() {
        $response = new StdResponse;
        $this->assertEquals('', $response->getBody());
        $this->assertNull($response->setBody('entity body'));
        $this->assertEquals('entity body', $response->getBody());
    }
    
    /**
     * @covers Artax\Http\StdMessage::getBody
     */
    public function testBodyAccessorReturnsStreamEntityBodyContentsIfAssigned() {
        $body = fopen('php://memory', 'r+');
        fwrite($body, 'test');
        rewind($body);
        
        $response = new StdResponse();
        $response->setBody($body);
        $this->assertEquals('test', $response->getBody());
        
        // Retrun the body string that was cached on the first access
        $this->assertEquals('test', $response->getBody());
    }
    
    /**
     * @covers Artax\Http\StdMessage::getBodyStream
     */
    public function testBodyStreamAccessorReturnsResource() {
        $body = fopen('php://memory', 'r+');
        fwrite($body, 'test');
        rewind($body);
        
        $response = new StdResponse();
        $response->setBody($body);
        $this->assertTrue(is_resource($response->getBodyStream()));
        $this->assertEquals('test', stream_get_contents($response->getBodyStream()));
    }
    
    /**
     * @covers Artax\Http\StdMessage::getBodyStream
     */
    public function testBodyStreamAccessorReturnsNullIfNotAResource() {
        $body = 'test';
        
        $response = new StdResponse();
        $response->setBody($body);
        $this->assertNull($response->getBodyStream());
    }
    
    /**
     * @covers Artax\Http\StdMessage::getHeader
     * @expectedException RuntimeException
     */
    public function testHeaderGetterThrowsExceptionOnInvalidHeaderRequest() {
        $response = new StdResponse;
        $response->getHeader('Doesnt-Exist');
    }
    
    /**
     * @covers Artax\Http\StdMessage::getHeader
     */
    public function testHeaderAccessors() {
        $response = new StdResponse;
        $this->assertNull($response->setHeader('Content-Type', 'text/html'));
        $this->assertEquals('text/html', $response->getHeader('Content-Type'));
        
        $this->assertNull($response->setHeader('content-type', 'application/json'));
        $this->assertEquals('application/json', $response->getHeader('CONTENT-TYPE'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::hasHeader
     */
    public function testHasHeaderReturnsBoolOnHeaderExistence() {
        $response = new StdResponse;
        $this->assertFalse($response->hasHeader('Content-Type'));
        $response->setHeader('Content-Type', 'text/html');
        $this->assertTrue($response->hasHeader('Content-TYPE'));
    }
    
    /**
     * @covers Artax\Http\StdMessage::getAllHeaders
     */
    public function testGetAllHeadersReturnsHeaderStorageArray() {
        $response = new StdResponse;
        $response->setHeader('Content-Type', 'text/html');
        $response->setHeader('Content-Length', 42);
        
        $expected = array(
            'CONTENT-TYPE' => 'text/html',
            'CONTENT-LENGTH' => 42
        );
        
        $this->assertEquals($expected, $response->getAllHeaders());
    }
    
    /**
     * @covers Artax\Http\StdMessage::setAllHeaders
     * @expectedException InvalidArgumentException
     */
    public function testSetAllHeadersThrowsExceptionOnInvalidIterable() {
        $response = new StdResponse();
        $response->setAllHeaders('not an iterable -- should throw exception');
    }
    
    public function provideInvalidRawHeaders() {
        return array(
            array('When in the chronicle of wasted time, I see descriptions of the fairest wights'),
            array('X-Responseed-By'),
            array("Content-Type: text/html\r\nContent-Length: 42"),
            array("Vary: Accept,Accept-Charset,\r\nAccept-Encoding")
        );
    }
    
    /**
     * @dataProvider provideInvalidRawHeaders
     * @covers Artax\Http\StdMessage::setRawHeader
     * @expectedException Spl\ValueException
     */
    public function testSetRawHeaderThrowsExceptionOnInvalidFormat($rawHeaderStr) {
        $response = new StdResponse;
        $response->setRawHeader($rawHeaderStr);
    }
    
    /**
     * @covers Artax\Http\StdResponse::setRawHeader
     */
    public function testSetRawHeaderParsesValidFormats() {
        $response = new StdResponse;
        
        $response->setRawHeader("Content-Type: text/html;q=0.9,\r\n\t*/*");
        $this->assertEquals('text/html;q=0.9, */*', $response->getHeader('Content-Type'));
        
        $response->setRawHeader('Content-Encoding: gzip');
        $this->assertEquals('gzip', $response->getHeader('Content-Encoding'));
        
        $response->setRawHeader("Content-Type: text/html;q=0.9,\r\n\t   application/json,\r\n */*");
        $this->assertEquals('text/html;q=0.9, application/json, */*',
            $response->getHeader('Content-Type')
        );
    }
}
