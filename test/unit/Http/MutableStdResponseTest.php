<?php

use Artax\Http\MutableStdResponse;

class MutableStdResponseTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\MutableStdResponse::setStatusCode
     */
    public function testSetStatusCodeAssignsValueAndReturnsNull() {
        $response = new MutableStdResponse();
        $this->assertNull($response->setStatusCode(400));
        $this->assertEquals(400, $response->getStatusCode());
    }
    
    /**
     * @covers Artax\Http\MutableStdResponse::setStatusDescription
     */
    public function testSetStatusDescriptionAssignsValueAndReturnsNull() {
        $response = new MutableStdResponse();
        $this->assertNull($response->setStatusDescription('OK'));
        $this->assertEquals('OK', $response->getStatusDescription());
    }
    
    /**
     * @covers Artax\Http\MutableStdResponse::removeBody
     */
    public function testSetRemoveBodyDoesAndReturnsNull() {
        $response = new MutableStdResponse();
        $this->assertNull($response->setBody('Glorfindel'));
        $this->assertEquals('Glorfindel', $response->getBody());
        $this->assertNull($response->removeBody());
        $this->assertNull($response->getBody());
    }
    
    /**
     * @covers Artax\Http\MutableStdResponse::setHttpVersion
     */
    public function testSetHttpVersionReturnsNull() {
        $response = new MutableStdResponse();
        $this->assertNull($response->setHttpVersion('1.0'));
        $this->assertEquals('1.0', $response->getHttpVersion());
    }
    
    /**
     * @covers Artax\Http\MutableStdResponse::setHeader
     */
    public function testSetHeaderCallsUnderlyingAssignHeadersMethodAndReturnsNull() {
        $response = new MutableStdResponse();
        $this->assertNull($response->setHeader('Content-Encoding:', 'gzip'));
        $this->assertEquals('gzip', $response->getHeader('content-encoding'));
    }
    
    /**
     * @covers Artax\Http\MutableStdResponse::setHeader
     * @covers Artax\Http\MutableStdResponse::setAllHeaders
     */
    public function testSetAllHeadersAssignsValuesAndReturnsNull() {
        $response = new MutableStdResponse();
        $headers = array('Content-Type'=>'text/html');
        $this->assertNull($response->setAllHeaders($headers));
        $this->assertEquals('text/html', $response->getHeader('CONTENT-TYPE'));
    }
    
    /**
     * @covers Artax\Http\MutableStdResponse::removeHeader
     */
    public function testRemoveHeaderDoesAndReturnsNull() {
        $response = new MutableStdResponse();
        $response->setHeader('Connection', 'close');
        $this->assertEquals('close', $response->getHeader('connection'));
        $this->assertNull($response->removeHeader('Connection'));
        $this->assertFalse($response->hasHeader('connection'));
    }
    
    /**
     * @covers Artax\Http\MutableStdResponse::__construct
     * @covers Artax\Http\MutableStdResponse::setBody
     */
    public function testSetBodyDoesAndReturnsNull() {
        $response = new MutableStdResponse();
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
            array('When in the chronicle of wasted time, I see descriptions of the fairest wights'),
            array('X-Responseed-By'),
            array("Content-Type: text/html\r\nContent-Length: 42"),
            array("Vary: Accept,Accept-Charset,\r\nAccept-Encoding")
        );
    }
    
    /**
     * @dataProvider provideInvalidRawHeaders
     * @covers Artax\Http\MutableStdResponse::setRawHeader
     * @expectedException Artax\Http\Exceptions\MessageParseException
     */
    public function testSetRawHeaderThrowsExceptionOnInvalidFormat($rawHeaderStr) {
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
