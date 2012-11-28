<?php

use Artax\Uri,
    Artax\Http\StdRequest,
    Artax\Http\ValueRequest;

class StdRequestTest extends PHPUnit_Framework_TestCase {
    
    public function provideInvalidMethodVerbs() {
        return array(
            array("GE\rT"),
            array("GE T"),
            array("POS\tT"),
            array(" \r")
        );
    }
    
    /**
     * @dataProvider provideInvalidMethodVerbs
     * @expectedException Spl\DomainException
     */
    public function testSetMethodThrowsExceptionOnInvalidMethodVerb($badMethod) {
        $request = new StdRequest();
        $request->setMethod($badMethod);
    }
    
    public function provideRequestsForRequestLineComparisons() {
        $return = array();
        
        // 0 --------------------------------------------------------------------------->
        $request = new StdRequest();
        $request->setMethod('GET');
        $request->setUri('http://localhost/some-url?myVar=42');
        $request->setProtocol('1.0');
        $expectedStartLine = 'GET /some-url?myVar=42 HTTP/1.0';
        $return[] = array($request, $expectedStartLine);
        
        // 1 --------------------------------------------------------------------------->
        $request = new StdRequest();
        $request->setMethod('CONNECT');
        $request->setUri('http://localhost:8096');
        $request->setProtocol('1.1');
        $expectedStartLine = 'CONNECT localhost:8096 HTTP/1.1';
        $return[] = array($request, $expectedStartLine);
        
        // x --------------------------------------------------------------------------->
        
        return $return;
    }
    
    /**
     * @dataProvider provideRequestsForRequestLineComparisons
     */
    public function testGetStartLine($request, $expectedStartLine) {
        $this->assertEquals($expectedStartLine, $request->getStartLine());
    }
    
    public function provideRequestsWithMissingStartLineProperties() {
        $return = array();
        
        // 0 --------------------------------------------------------------------------->
        $request = new StdRequest;
        $request->setMethod('GET');
        $request->setProtocol('1.1');
        
        $return[] = array($request); // missing URI
        
        // 1 --------------------------------------------------------------------------->
        $request = new StdRequest;
        $request->setUri('http://localhost');
        $request->setProtocol('1.1');
        
        $return[] = array($request); // missing method
        
        // 2 --------------------------------------------------------------------------->
        $request = new StdRequest;
        $request->setUri('http://localhost');
        $request->setMethod('GET');
        
        $return[] = array($request); // missing http version
        
        // x --------------------------------------------------------------------------->
        
        return $return;
        
    }
    
    /**
     * @dataProvider provideRequestsWithMissingStartLineProperties
     * @expectedException Spl\DomainException
     */
    public function testGetStartLineThrowsExceptionOnMissingProperties($incompleteRequest) {
        $incompleteRequest->getStartLine();
    }
    
    /**
     * @covers Artax\Http\StdRequest::getMethod
     * @covers Artax\Http\StdRequest::setMethod
     * @covers Artax\Http\StdRequest::assignMethod
     */
    public function testMethodGetterReturnsMethodProperty() {
        $request = new StdRequest();
        
        $this->assertNull($request->getMethod());
        $this->assertNull($request->setMethod('DELETE'));
        $this->assertEquals('DELETE', $request->getMethod());
    }
    
    /**
     * @covers Artax\Http\StdRequest::getUri
     */
    public function testUriGetterReturnsUriToStringResult() {
        $request = new StdRequest();
        
        $this->assertNull($request->getUri());
        $this->assertNull($request->setUri('http://something'));
        $this->assertEquals('http://something', $request->getUri());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__toString
     */
    public function testToStringReturnsRawHttpMessage() {
        $body = 'entity body';
        
        $request = new StdRequest();
        $request->setMethod('POST');
        $request->setUri('http://localhost/someUrl?someVar=42');
        $request->setProtocol('1.1');
        $request->setAllHeaders(array(
            'Host' => 'localhost',
            'Content-Type' => 'test',
            'Content-Length' => 11
        ));
        $request->setBody($body);
        
        $expected = "POST /someUrl?someVar=42 HTTP/1.1\r\n" .
                    "Host: localhost\r\n" .
                    "Content-Type: test\r\n" . 
                    "Content-Length: 11\r\n\r\n" .
                    $body;
        
        $this->assertEquals($expected, $request->__toString());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__toString
     */
    public function testToStringConnectOutput() {
        $request = new StdRequest();
        
        $request->setMethod('CONNECT');
        $request->setUri('http://localhost:8096');
        $request->setProtocol('1.1');
        $request->setHeader('Content-Type', 'test');
        
        $expected = "CONNECT localhost:8096 HTTP/1.1\r\n" .
                    "Content-Type: test\r\n\r\n";
        
        $this->assertEquals($expected, $request->__toString());
    }
    
    /**
     * @covers Artax\Http\StdRequest::getBody
     */
    public function testGetBodyReturnsAssignedBody() {
        $request = new StdRequest();
        
        $request->setBody('request body');
        $this->assertEquals('request body', $request->getBody());
    }
    
    public function provideNonRequestImportParams() {
        return array(
            array(42),
            array('Request'),
            array(new StdClass)
        );
    }
    
    /**
     * @dataProvider provideNonRequestImportParams
     * @expectedException Spl\TypeException
     */
    public function testImportThrowsExceptionOnNonRequestParameter($badMessage) {
        $request = new StdRequest();
        $request->import($badMessage);
    }
    
    public function testImport() {
        $method = 'GET';
        $uri = 'http://localhost/test?var1=one&var2=2';
        $protocol = '1.1';
        $headers = array(
            'Host' => 'localhost',
            'Content-Length' => 5
        );
        
        $bodyContent = 'woot!';
        $body = fopen('php://memory', 'r+');
        fwrite($body, $bodyContent);
        rewind($body);
        
        
        $value = new ValueRequest($method, $uri, $protocol, $headers, $body);
        
        $request = new StdRequest();
        $request->import($value);
        
        
        $this->assertEquals($method, $request->getMethod());
        $this->assertEquals($uri, $request->getUri());
        $this->assertEquals($protocol, $request->getProtocol());
        $this->assertEquals($headers['Host'], $request->getCombinedHeader('Host'));
        $this->assertEquals($headers['Content-Length'], $request->getCombinedHeader('Content-Length'));
        $this->assertEquals($body, $request->getBody());
    }
    
    public function provideUnexportableRequests() {
        $return = array();
        
        // 0 -------------------------------------------------------------------------------------->
        $request = new StdRequest();
        $request->setMethod('GET');
        $request->setProtocol(1.1);
        // $request->setUri('http://localhost');
        
        $return[] = array($request);
        
        // 1 -------------------------------------------------------------------------------------->
        $request = new StdRequest();
        $request->setMethod('GET');
        //$request->setProtocol(1.1);
        $request->setUri('http://localhost');
        
        $return[] = array($request);
        
        // 2 -------------------------------------------------------------------------------------->
        $request = new StdRequest();
        //$request->setMethod('GET');
        $request->setProtocol(1.1);
        $request->setUri('http://localhost');
        
        $return[] = array($request);
        
        // x -------------------------------------------------------------------------------------->
        
        return $return;
    }
    
    /**
     * @dataProvider provideUnexportableRequests
     * @expectedException Spl\DomainException
     */
    public function testExportThrowsExceptionIfRequiredPropertiesNotSet($unexportableRequest) {
        $unexportableRequest->export();
    }
    
    public function testExport() {
        $method = 'GET';
        $uri = 'http://localhost/test?var1=one&var2=2';
        $protocol = '1.1';
        $headers = array(
            'Host' => 'localhost',
            'Content-Length' => 5
        );
        
        $bodyContent = 'woot!';
        $body = fopen('php://memory', 'r+');
        fwrite($body, $bodyContent);
        rewind($body);
        
        $request = new StdRequest();
        $request->setMethod($method);
        $request->setProtocol($protocol);
        $request->setUri($uri);
        $request->setAllHeaders($headers);
        $request->setBody($body);
        
        $value = $request->export();
        
        
        $this->assertEquals($method, $value->getMethod());
        $this->assertEquals($uri, $value->getUri());
        $this->assertEquals($protocol, $value->getProtocol());
        $this->assertEquals($headers['Host'], $request->getCombinedHeader('Host'));
        $this->assertEquals($headers['Content-Length'], $request->getCombinedHeader('Content-Length'));
        $this->assertEquals($body, $value->getBody());
    }
}