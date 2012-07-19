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
    
    /**
     * @covers Artax\Http\StdResponse::getHeader
     * @expectedException RuntimeException
     */
    public function testHeaderGetterThrowsExceptionOnInvalidHeaderRequest() {
        $response = new StdResponse;
        $response->getHeader('Doesnt-Exist');
    }
    
    /**
     * @covers Artax\Http\StdResponse::getHeader
     * @covers Artax\Http\StdResponse::setHeader
     */
    public function testHeaderAccessors() {
        $response = new StdResponse;
        $this->assertNull($response->setHeader('Content-Type', 'text/html'));
        $this->assertEquals('text/html', $response->getHeader('Content-Type'));
        
        $this->assertNull($response->setHeader('content-type', 'application/json'));
        $this->assertEquals('application/json', $response->getHeader('CONTENT-TYPE'));
    }
    
    /**
     * @covers Artax\Http\StdResponse::hasHeader
     */
    public function testHasHeaderReturnsBoolOnHeaderExistence() {
        $response = new StdResponse;
        $this->assertFalse($response->hasHeader('Content-Type'));
        $response->setHeader('Content-Type', 'text/html');
        $this->assertTrue($response->hasHeader('Content-TYPE'));
    }
    
    /**
     * @covers Artax\Http\StdResponse::getAllHeaders
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
     * @covers Artax\Http\StdResponse::setAllHeaders
     * @expectedException InvalidArgumentException
     */
    public function testSetAllHeadersThrowsExceptionOnInvalidParameter() {
        $response = new StdResponse;
        $response->setAllHeaders('not an iterable');
    }
    
    /**
     * @covers Artax\Http\StdResponse::setAllHeaders
     */
    public function testSetAllHeadersCallsSetHeaderForEachHeader() {
        $response = $this->getMock('Artax\\Http\\StdResponse', array('setHeader'));
        
        $headers = array(
            'CONTENT-TYPE' => 'text/html',
            'CONTENT-LENGTH' => 42
        );
        
        $response->expects($this->exactly(2))
                 ->method('setHeader');
        $response->setAllHeaders($headers);
    }
    
    /**
     * @covers Artax\Http\StdResponse::send
     * @covers Artax\Http\StdResponse::wasSent
     * @runInSeparateProcess
     */
    public function testSendOutputsHeadersAndReturnsNull() {
        $response = new StdResponse;
        $response->setAllHeaders(array(
            'CONTENT-TYPE' => 'text/html',
            'CONTENT-LENGTH' => 42
        ));
        
        $this->assertFalse($response->wasSent());
        $this->AssertNull($response->send());
        $this->assertTrue($response->wasSent());
    }
    
    public function provideInvalidStartLines() {
        return array(
            array('HTTP/1 200 OK'),
            array('HTTP 404 Not Found'),
            array('HTTP1.1 405 Method Not Allowed'),
            array("HTTP/1.0 200\nOK"),
            array("HTTP/1.0 2000 OK"),
            array("HTTP/1.0 20 OK")
        );
    }
    
    /**
     * @dataProvider provideInvalidStartLines
     * @covers Artax\Http\StdResponse::setStartLine
     * @expectedException InvalidArgumentException
     */
    public function testSetStartLineThrowsExceptionOnInvalidArgumentFormat($startLineStr) {
        $response = new StdResponse;
        $response->setStartLine($startLineStr);
    }
    
    /**
     * @covers Artax\Http\StdResponse::setStartLine
     */
    public function testSetStartLineAssignsComponentProperties() {
        $response = new StdResponse;
        $response->setStartLine('HTTP/1.0 500 Internal Server Error');
        
        $this->assertEquals('1.0', $response->getHttpVersion());
        $this->assertEquals('500', $response->getStatusCode());
        $this->assertEquals('Internal Server Error', $response->getStatusDescription());
    }
    
    public function provideInvalidRawHeaders() {
        return array(
            array('1234 Get your woman on the floor'),
            array('X-Requested-By'),
            array("Content-Type: text/html\r\nContent-Length: 42"),
            array("Vary: Accept,Accept-Charset,\r\nAccept-Encoding")
        );
    }
    
    /**
     * @dataProvider provideInvalidRawHeaders
     * @covers Artax\Http\StdResponse::setRawHeader
     * @expectedException InvalidArgumentException
     */
    public function testSetRawHeaderThrowsExceptionOnInvalidArgumentFormat($rawHeaderStr) {
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
