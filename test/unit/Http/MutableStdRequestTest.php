<?php

use Artax\Http\MutableStdRequest,
    Artax\Http\StdRequest,
    Artax\Http\StdUri;

class MutableStdRequestTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\MutableStdRequest::__construct
     * @covers Artax\Http\MutableStdRequest::setUri
     */
    public function testSetUriBuildsUriObjectAndReturnsNull() {
        $request = new MutableStdRequest();
        $uri = new StdUri('http://www.nytimes.com');
        $this->assertNull($request->setUri($uri));
        $this->assertEquals($uri, $request->getUri());
        
        $request->setUri('http://www.nytimes.com');
        $this->assertEquals('http', $request->getScheme());
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::__construct
     * @covers Artax\Http\MutableStdRequest::setUri
     */
    public function testSetUriAssignsQueryParameterValues() {
        $request = new MutableStdRequest();
        $uri = new StdUri('http://www.nytimes.com/page?var1=42&var2=99');
        $request->setUri($uri);
        $this->assertEquals(42, $request->getQueryParameter('var1'));
        $this->assertEquals(99, $request->getQueryParameter('var2'));
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::__construct
     * @covers Artax\Http\MutableStdRequest::setMethod
     */
    public function testSetMethodUpperCasesVerbAndReturnsNull() {
        $request = new MutableStdRequest();
        $this->assertNull($request->setMethod('test'));
        $this->assertEquals('TEST', $request->getMethod());
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::__construct
     * @covers Artax\Http\MutableStdRequest::setMethod
     */
    public function testSetMethodAssignsBodyParameterValues() {
        $request = new MutableStdRequest();
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->setUri('http://www.nytimes.com');
        $request->setBody('x=42&y=99');
        $request->setMethod('POST');
        
        $this->assertEquals(array('x'=>'42', 'y'=>'99'), $request->getAllBodyParameters());
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::__construct
     * @covers Artax\Http\MutableStdRequest::setHttpVersion
     */
    public function testSetHttpVersionReturnsNull() {
        $request = new MutableStdRequest();
        $this->assertNull($request->setHttpVersion('1.0'));
        $this->assertEquals('1.0', $request->getHttpVersion());
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::__construct
     * @covers Artax\Http\MutableStdRequest::setHeader
     */
    public function testSetHeaderTrimsTrailingColonThenAssignsValueAndReturnsNull() {
        $request = new MutableStdRequest();
        $this->assertNull($request->setHeader('Accept:', 'text/*'));
        $this->assertEquals('text/*', $request->getHeader('accept'));
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::__construct
     * @covers Artax\Http\MutableStdRequest::setHeader
     * @covers Artax\Http\MutableStdRequest::setAllHeaders
     */
    public function testSetAllHeadersAssignsValuesAndReturnsNull() {
        $request = new MutableStdRequest();
        $this->assertNull($request->setAllHeaders(array('Accept'=>'text/*')));
        $this->assertEquals('text/*', $request->getHeader('accept'));
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::__construct
     * @covers Artax\Http\MutableStdRequest::setHeader
     * @covers Artax\Http\MutableStdRequest::setAllHeaders
     * @expectedException InvalidArgumentException
     */
    public function testSetAllHeadersThrowsExceptionOnInvalidIterable() {
        $request = new MutableStdRequest();
        $request->setAllHeaders('not iterable');
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::__construct
     * @covers Artax\Http\MutableStdRequest::removeHeader
     */
    public function testRemoveHeaderDoesAndReturnsNull() {
        $request = new MutableStdRequest();
        $request->setHeader('Accept', 'text/*');
        $this->assertEquals('text/*', $request->getHeader('accept'));
        $this->assertNull($request->removeHeader('Accept'));
        $this->assertFalse($request->hasHeader('Accept'));
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::__construct
     * @covers Artax\Http\MutableStdRequest::setBody
     */
    public function testSetBodyDoesAndReturnsNull() {
        $request = new MutableStdRequest();
        $this->assertNull($request->setBody('We few, we happy few.'));
        $this->assertEquals('We few, we happy few.', $request->getBody());
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::__construct
     * @covers Artax\Http\MutableStdRequest::setBody
     */
    public function testSetBodyAssignsBodyParameterValues() {
        $request = new MutableStdRequest();
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->setMethod('POST');
        $request->setUri('http://www.nytimes.com');
        $request->setBody('x=42&y=99');
        
        $this->assertEquals(array('x'=>'42', 'y'=>'99'), $request->getAllBodyParameters());
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::clearAll
     * @covers Artax\Http\MutableStdRequest::getUri
     */
    public function testClearAllDoesAndReturnsNull() {
        $request = new MutableStdRequest();
        $request->setMethod('POST');
        $request->setUri('http://www.kumqat.com/widgets?var=42');
        $request->setHeader('Accept', 'test/plain');
        $request->setBody('bodyVar=42');
        
        $request->clearAll();
        
        $this->assertNull($request->getUri());
        $this->assertEquals(null, $request->getMethod());
        $this->assertEquals(array(), $request->getAllHeaders());
        $this->assertEquals(array(), $request->getAllQueryParameters());
        $this->assertEquals(array(), $request->getAllBodyParameters());
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::populateFromRequest
     */
    public function testPopulateFromRequestDoesAndReturnsNull() {
        $stdRequest = new StdRequest(
            'http://www.kumqat.com/widgets?var=42',
            'POST',
            array('Content-Type' => 'application/x-www-form-urlencoded'),
            'bodyVar=42'
        );
        
        $mutableRequest = new MutableStdRequest();
        $this->assertNull($mutableRequest->populateFromRequest($stdRequest));
        
        $this->assertEquals(array('var'=>'42'), $mutableRequest->getAllQueryParameters());
        $this->assertEquals(array('bodyVar'=>'42'), $mutableRequest->getAllBodyParameters());
        
        $this->assertEquals($stdRequest->getHttpVersion(),$mutableRequest->getHttpVersion());
        $this->assertEquals($stdRequest->getUri(),$mutableRequest->getUri());
        $this->assertEquals($stdRequest->getMethod(), $mutableRequest->getMethod());
        $this->assertEquals($stdRequest->getBody(), $mutableRequest->getBody());
        $this->assertEquals($stdRequest->getAllHeaders(), $mutableRequest->getAllHeaders());
        $this->assertEquals($stdRequest->getAllQueryParameters(), $mutableRequest->getAllQueryParameters());
        $this->assertEquals($stdRequest->getAllBodyParameters(), $mutableRequest->getAllBodyParameters());
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
     * @covers Artax\Http\MutableStdRequest::setRawHeader
     * @expectedException InvalidArgumentException
     */
    public function testSetRawHeaderThrowsExceptionOnInvalidArgumentFormat($rawHeaderStr) {
        $request = new MutableStdRequest;
        $request->setRawHeader($rawHeaderStr);
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::setRawHeader
     */
    public function testSetRawHeaderParsesValidFormats() {
        $request = new MutableStdRequest;
        
        $request->setRawHeader("Content-Type: text/html;q=0.9,\r\n\t*/*");
        $this->assertEquals('text/html;q=0.9, */*', $request->getHeader('Content-Type'));
        
        $request->setRawHeader('Content-Encoding: gzip');
        $this->assertEquals('gzip', $request->getHeader('Content-Encoding'));
        
        $request->setRawHeader("Content-Type: text/html;q=0.9,\r\n\t   application/json,\r\n */*");
        $this->assertEquals('text/html;q=0.9, application/json, */*',
            $request->getHeader('Content-Type')
        );
    }
    
    public function provideInvalidRequestsForValidation() {
        
        $noUri = new MutableStdRequest;
        $noUri->setMethod('GET');
        
        $noMethod = new MutableStdRequest;
        $noMethod->setUri('http://localhost');
        
        $entityBodyDisallowed = new MutableStdRequest;
        $entityBodyDisallowed->setBody('test');
        $entityBodyDisallowed->setUri('http://localhost');
        $entityBodyDisallowed->setMethod('GET');
    
        return array(
            array($noUri),
            array($noMethod),
            array($entityBodyDisallowed)
        );
    }
    
    /**
     * @dataProvider provideInvalidRequestsForValidation
     * @covers Artax\Http\MutableStdRequest::validateMessage
     * @expectedException Artax\Http\Exceptions\MessageValidationException
     */
    public function testValidateMessageThrowsExceptionOnInvalidRequestValues($request) {
        $request->validateMessage();
    }
    
    /**
     * @dataProvider provideInvalidRequestsForValidation
     * @covers Artax\Http\MutableStdRequest::validateMessage
     * @covers Artax\Http\MutableStdRequest::__toString
     */
    public function testToStringReturnsEmptyStringOnInvalidRequestValues($request) {
        $this->assertEquals('', $request->__toString());
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::validateMessage
     */
    public function testValidateMessageReturnsNullIfRequestIsNotBroken() {
        
        $request = new MutableStdRequest;
        $request->setBody('test');
        $request->setUri('http://localhost');
        $request->setMethod('POST');
        
        $this->assertNull($request->validateMessage());
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::validateMessage
     * @covers Artax\Http\MutableStdRequest::__toString
     */
    public function testToStringReturnsRawMessageStringOnSuccessfulValidation() {
        $request = new MutableStdRequest;
        $request->setBody('test');
        $request->setUri('http://localhost');
        $request->setMethod('POST');
        $request->setHttpVersion('1.0');
        $request->setHeader('Content-Type', 'text/plain');
        
        $expected = "POST / HTTP/1.0\r\n";
        $expected.= "HOST: localhost\r\n";
        $expected.= "CONTENT-LENGTH: " . strlen('test') . "\r\n";
        $expected.= "CONTENT-TYPE: text/plain\r\n";
        $expected.= "\r\n";
        $expected.= "test";
        
        $this->assertEquals($expected, $request->__toString());
    }
    
    /**
     * @covers Artax\Http\MutableStdRequest::validateMessage
     * @covers Artax\Http\MutableStdRequest::__toString
     * @covers Artax\Http\MutableStdRequest::populateFromRawMessage
     */
    public function testPopulateFromRawMessageDoesAndReturnsNull() {
        $request = new MutableStdRequest;
        $request->setBody('test');
        $request->setUri('http://localhost');
        $request->setMethod('POST');
        $request->setHttpVersion('1.0');
        $request->setHeader('Content-Type', 'text/plain');
        
        $rawData = "POST / HTTP/1.0\r\n";
        $rawData.= "HOST: localhost\r\n";
        $rawData.= "CONTENT-LENGTH: " . strlen('test') . "\r\n";
        $rawData.= "CONTENT-TYPE: text/plain\r\n";
        $rawData.= "\r\n";
        $rawData.= "test";
        
        $populated = new MutableStdRequest;
        $populated->populateFromRawMessage($rawData);
        
        $this->assertEquals($rawData, $request->__toString());
        $this->assertEquals($populated->__toString(), $request->__toString());
    }
    
}
