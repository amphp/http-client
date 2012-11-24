<?php

use Artax\Http\StdResponse,
    Artax\Http\ValueResponse;

/**
 * @covers Artax\Http\StdResponse
 * @covers Artax\Http\Response
 * @covers Artax\Http\MutableResponse
 * @covers Artax\Http\StdMessage
 * @covers Artax\Http\MutableMessage
 */
class StdResponseTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\StdResponse::__toString
     */
    public function testToStringBuildsRawHttpResponseMessage() {
        $response = new StdResponse();
        
        $response->setProtocol(1.1);
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
        
        $this->assertEquals($expected, (string) $response);
    }
    
    /**
     * @covers Artax\Http\StdResponse::getStatusCode
     */
    public function testStatusCodeAccessorMethodReturnsStatusCode() {
        $response = new StdResponse();
        $this->assertNull($response->getStatusCode());
        $response->setStatusCode(404);
        $this->assertEquals(404, $response->getStatusCode());
    }
    
    /**
     * @covers Artax\Http\StdResponse::getReasonPhrase
     */
    public function testGetReasonPhrase() {
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
        $response->setProtocol('1.0');
        
        $this->assertEquals('HTTP/1.0 405 Method Not Allowed', $response->getStartLine());
    }
    
    /**
     * @covers Artax\Http\StdResponse::setStatusCode
     */
    public function testSetStatusCodeAssignsValueAndReturnsNull() {
        $response = new StdResponse();
        
        $this->assertNull($response->getStatusCode());
        $this->assertNull($response->setStatusCode(400));
        $this->assertEquals(400, $response->getStatusCode());
    }
    
    public function provideInvalidStatusCodes() {
        return array(
            array(099),
            array('1xx'),
            array(600),
            array(42),
            array(null)
        );
    }
    
    /**
     * @dataProvider provideInvalidStatusCodes
     * @expectedException Spl\DomainException
     */
    public function testSetStatusCodeThrowsExceptionOnInvalidValue($badStatus) {
        $response = new StdResponse();
        $response->setStatusCode($badStatus);
    }
    
    /**
     * @covers Artax\Http\StdResponse::setReasonPhrase
     */
    public function testSetReasonPhraseReturnsNull() {
        $response = new StdResponse();
        
        $this->assertNull($response->getReasonPhrase());
        $this->assertNull($response->setReasonPhrase('OK'));
        $this->assertEquals('OK', $response->getReasonPhrase());
    }
    
    public function provideInvalidReasonPhrases() {
        return array(
            array("has illegal \r control char"),
            array("has illegal \n control char")
        );
    }
    
    /**
     * @dataProvider provideInvalidReasonPhrases
     * @expectedException Spl\DomainException
     */
    public function testSetReasonPhraseThrowsExceptionOnInvalidValue($badReason) {
        $response = new StdResponse();
        $response->setReasonPhrase($badReason);
    }
    
    public function testThatEmptyReasonPhraseIsAllowed() {
        $response = new StdResponse();
        $response->setReasonPhrase("\r\n");
        $this->assertEquals('', $response->getReasonPhrase());
    }
    
    /**
     * @covers Artax\Http\StdResponse::setProtocol
     */
    public function testSetProtocolReturnsNull() {
        $response = new StdResponse();
        
        $this->assertNull($response->getProtocol());
        $this->assertNull($response->setProtocol('1.0'));
        $this->assertEquals('1.0', $response->getProtocol());
    }
    
    /**
     * @covers Artax\Http\StdResponse::setHeader
     */
    public function testSetHeaderCallsUnderlyingAssignHeadersMethodAndReturnsNull() {
        $response = new StdResponse();
        $this->assertNull($response->setHeader('Content-Encoding:', 'gzip'));
        $this->assertEquals('gzip', $response->getCombinedHeader('content-encoding'));
    }
    
    /**
     * @covers Artax\Http\StdResponse::setHeader
     * @covers Artax\Http\StdResponse::setAllHeaders
     */
    public function testSetAllHeadersReturnsNull() {
        $response = new StdResponse();
        $headers = array('Content-Type'=>'text/html');
        $this->assertNull($response->setAllHeaders($headers));
        $this->assertEquals('text/html', $response->getCombinedHeader('CONTENT-TYPE'));
    }
    
    /**
     * @covers Artax\Http\StdResponse::removeHeader
     */
    public function testRemoveHeaderReturnsNull() {
        $response = new StdResponse();
        
        $this->assertFalse($response->hasHeader('connection'));
        $response->setHeader('Connection', 'close');
        $this->assertTrue($response->hasHeader('coNNeCTion'));
        
        $this->assertEquals('close', $response->getCombinedHeader('connection'));
        $this->assertNull($response->removeHeader('Connection'));
        $this->assertFalse($response->hasHeader('connection'));
    }
    
    /**
     * @covers Artax\Http\StdResponse::setBody
     */
    public function testSetBodyReturnsNull() {
        $response = new StdResponse();
        
        $this->assertNull($response->setBody('We few, we happy few.'));
        $this->assertEquals('We few, we happy few.', $response->getBody());
    }
    
    public function provideInvalidImportResponses() {
        return array(
            array(42),
            array('ValueResponse'),
            array(null),
            array(''),
            array(new StdClass)
        );
    }
    
    /**
     * @dataProvider provideInvalidImportResponses
     * @expectedException Spl\TypeException
     */
    public function testImportThrowsExceptionOnInvalidType($badResponse) {
        $response = new StdResponse;
        $response->import($badResponse);
    }
    
    public function testImport() {
        $status = '200';
        $reason = 'OK';
        $protocol = '1.1';
        $headers = array(
            'Some-Header' => 'some value',
            'Content-Length' => 5
        );
        
        $bodyContent = 'woot!';
        $body = fopen('php://memory', 'r+');
        fwrite($body, $bodyContent);
        rewind($body);
        
        
        $value = new ValueResponse($protocol, $status, $reason, $headers, $body);
        
        $response = new StdResponse();
        $response->import($value);
        
        
        $this->assertEquals($status, $response->getStatusCode());
        $this->assertEquals($reason, $response->getReasonPhrase());
        $this->assertEquals($protocol, $response->getProtocol());
        $this->assertEquals($headers['Some-Header'], $response->getCombinedHeader('Some-Header'));
        $this->assertEquals($headers['Content-Length'], $response->getCombinedHeader('Content-Length'));
        $this->assertEquals($body, $response->getBody());
    }
    
    public function provideUnexportableResponses() {
        $return = array();
        
        // 0 -------------------------------------------------------------------------------------->
        $response = new StdResponse();
        $response->setProtocol(1.1);
        //$response->setStatusCode(200);
        
        $return[] = array($response);
        
        // 1 -------------------------------------------------------------------------------------->
        $response = new StdResponse();
        //$response->setProtocol(1.1);
        $response->setStatusCode(200);
        
        $return[] = array($response);
        
        // x -------------------------------------------------------------------------------------->
        
        return $return;
    }
    
    /**
     * @dataProvider provideUnexportableResponses
     * @expectedException Spl\DomainException
     */
    public function testExportThrowsExceptionIfRequiredPropertiesNotSet($unexportableResponse) {
        $unexportableResponse->export();
    }
}
