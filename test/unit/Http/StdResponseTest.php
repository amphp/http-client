<?php

use Artax\Http\StdResponse;

class StdResponseTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\StdResponse::__toString
     */
    public function testToStringBuildsRawHttpResponseMessage() {
        $response = new StdResponse();
        $response->setStatusCode(200);
        $response->setReasonPhrase('OK');
        $response->setAllHeaders(array(
            'CONTENT-TYPE' => 'text/html',
            'CONTENT-LENGTH' => 42
        ));
        $response->setBody('test');
        
        $expected = '' .
            "HTTP/1.1 200 OK\r\n" .
            "CONTENT-TYPE: text/html\r\n" .
            "CONTENT-LENGTH: 42\r\n" .
            "\r\n" .
            "test"
        ;
        
        $this->assertEquals($expected, $response->__toString());
    }
    
    /**
     * @covers Artax\Http\StdResponse::getStatusCode
     */
    public function testStatusCodeAccessorMethodReturnsStatusCode() {
        $response = new StdResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $response->setStatusCode(404);
        $this->assertEquals(404, $response->getStatusCode());
    }
    
    /**
     * @covers Artax\Http\StdResponse::getReasonPhrase
     */
    public function testReasonPhraseAccessorReturnsDescription() {
        $response = new StdResponse();
        $this->assertNull($response->getReasonPhrase());
        $response->setReasonPhrase('Not Found');
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }
    
    /**
     * @covers Artax\Http\StdResponse::getStartLine
     */
    public function testStartLineGetterReturnsRawStartLineString() {
        $response = new StdResponse();
        $response->setStatusCode(405);
        $response->setReasonPhrase('Method Not Allowed');
        $response->setHttpVersion('1.0');
        
        $this->assertEquals('HTTP/1.0 405 Method Not Allowed', $response->getStartLine());
    }
    
    /**
     * @covers Artax\Http\StdResponse::getStartLineAndHeaders
     */
    public function testGetRawStartLineAndHeaders() {
        $response = new StdResponse();
        $response->setStartLine('HTTP/1.1 200 OK');
        $response->setAllHeaders(array(
            'Content-Length' => 5,
            'Connection' => 'close'
        ));
        $response->setBody('woot!');
        
        $expected = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 5\r\n" .
            "Connection: close\r\n" .
            "\r\n"
        ;
        
        $this->assertEquals($expected, $response->getStartLineAndHeaders());
    }
    
    /**
     * @covers Artax\Http\StdResponse::setStatusCode
     */
    public function testSetStatusCodeAssignsValueAndReturnsNull() {
        $response = new StdResponse();
        $this->assertNull($response->setStatusCode(400));
        $this->assertEquals(400, $response->getStatusCode());
    }
    
    /**
     * @covers Artax\Http\StdResponse::setReasonPhrase
     */
    public function testSetReasonPhraseAssignsValueAndReturnsNull() {
        $response = new StdResponse();
        $this->assertNull($response->setReasonPhrase('OK'));
        $this->assertEquals('OK', $response->getReasonPhrase());
    }
    
    /**
     * @covers Artax\Http\StdResponse::setHttpVersion
     */
    public function testSetHttpVersionReturnsNull() {
        $response = new StdResponse();
        $this->assertNull($response->setHttpVersion('1.0'));
        $this->assertEquals('1.0', $response->getHttpVersion());
    }
    
    /**
     * @covers Artax\Http\StdResponse::setHeader
     */
    public function testSetHeaderCallsUnderlyingAssignHeadersMethodAndReturnsNull() {
        $response = new StdResponse();
        $this->assertNull($response->setHeader('Content-Encoding:', 'gzip'));
        $this->assertEquals('gzip', $response->getHeader('content-encoding'));
    }
    
    /**
     * @covers Artax\Http\StdResponse::setHeader
     * @covers Artax\Http\StdResponse::setAllHeaders
     */
    public function testSetAllHeadersAssignsValuesAndReturnsNull() {
        $response = new StdResponse();
        $headers = array('Content-Type'=>'text/html');
        $this->assertNull($response->setAllHeaders($headers));
        $this->assertEquals('text/html', $response->getHeader('CONTENT-TYPE'));
    }
    
    /**
     * @covers Artax\Http\StdResponse::removeHeader
     */
    public function testRemoveHeaderDoesAndReturnsNull() {
        $response = new StdResponse();
        $response->setHeader('Connection', 'close');
        $this->assertEquals('close', $response->getHeader('connection'));
        $this->assertNull($response->removeHeader('Connection'));
        $this->assertFalse($response->hasHeader('connection'));
    }
    
    /**
     * @covers Artax\Http\StdResponse::setBody
     */
    public function testSetBodyAssignsEntityBodyAndReturnsNull() {
        $response = new StdResponse();
        $this->assertNull($response->setBody('We few, we happy few.'));
        $this->assertEquals('We few, we happy few.', $response->getBody());
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
     * @expectedException Spl\ValueException
     */
    public function testSetStartLineThrowsExceptionOnInvalidArgumentFormat($startLineStr) {
        $response = new StdResponse();
        $response->setStartLine($startLineStr);
    }
    
    /**
     * @covers Artax\Http\StdResponse::setStartLine
     */
    public function testSetStartLineAssignsComponentProperties() {
        $response = new StdResponse();
        $response->setStartLine('HTTP/1.0 500 Internal Server Error');
        
        $this->assertEquals('1.0', $response->getHttpVersion());
        $this->assertEquals('500', $response->getStatusCode());
        $this->assertEquals('Internal Server Error', $response->getReasonPhrase());
    }
}
