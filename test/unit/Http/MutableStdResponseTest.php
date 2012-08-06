<?php

use Artax\Http\MutableStdResponse;

class MutableStdResponseTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\MutableStdResponse::setAllHeaders
     * @expectedException InvalidArgumentException
     */
    public function testSetAllHeadersThrowsExceptionOnInvalidParameter() {
        $response = new MutableStdResponse;
        $response->setAllHeaders('not an iterable');
    }
    
    /**
     * @covers Artax\Http\MutableStdResponse::removeHeader
     */
    public function testRemoveHeaderClearsSpecifiedHeader() {
        $response = new MutableStdResponse;
        $response->setHeader('Content-Type', 'text/html');
        $this->assertTrue($response->hasHeader('Content-TYPE'));
        $response->removeHeader('Content-Type');
        $this->assertFalse($response->hasHeader('Content-TYPE'));
    }
    
    /**
     * @covers Artax\Http\MutableStdResponse::setAllHeaders
     */
    public function testSetAllHeadersCallsSetHeaderForEachHeader() {
        $response = $this->getMock('Artax\\Http\\MutableStdResponse', array('setHeader'));
        
        $headers = array(
            'CONTENT-TYPE' => 'text/html',
            'CONTENT-LENGTH' => 42
        );
        
        $response->expects($this->exactly(2))
                 ->method('setHeader');
        $response->setAllHeaders($headers);
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
     * @covers Artax\Http\MutableStdResponse::setStartLine
     * @expectedException Artax\Http\Exceptions\MessageParseException
     */
    public function testSetStartLineThrowsExceptionOnInvalidArgumentFormat($startLineStr) {
        $response = new MutableStdResponse;
        $response->setStartLine($startLineStr);
    }
    
    /**
     * @covers Artax\Http\MutableStdResponse::setStartLine
     */
    public function testSetStartLineAssignsComponentProperties() {
        $response = new MutableStdResponse;
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
     * @covers Artax\Http\MutableStdResponse::setRawHeader
     * @expectedException Artax\Http\Exceptions\MessageParseException
     */
    public function testSetRawHeaderThrowsExceptionOnInvalidArgumentFormat($rawHeaderStr) {
        $response = new MutableStdResponse;
        $response->setRawHeader($rawHeaderStr);
    }
    
    /**
     * @covers Artax\Http\MutableStdResponse::setRawHeader
     */
    public function testSetRawHeaderParsesValidFormats() {
        $response = new MutableStdResponse;
        
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
