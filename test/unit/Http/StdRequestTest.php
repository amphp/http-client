<?php

use Artax\Http\StdRequest,
    Artax\Uri;

class StdRequestTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::parseParametersFromString
     */
    public function testThatNoQueryParamsAreParsedOnEmptyUriQueryString() {
        $uri = new Uri('http://localhost');
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals(array(), $request->getAllQueryParameters());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getUri
     */
    public function testUriGetterReturnsComposedUriToStringResult() {
        $uri = new Uri('http://something');
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('http://something', $request->getUri());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getScheme
     */
    public function testSchemeGetterReturnsComposedUriGetSchemeResult() {
        $uri = new Uri('https://localhost');
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('https', $request->getScheme());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getHost
     */
    public function testHostGetterReturnsComposedUriGetHostResult() {
        $uri = new Uri('http://localhost:8096');
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('localhost', $request->getHost());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getPort
     */
    public function testPortGetterReturnsComposedUriGetPortResult() {
        $uri = new Uri('http://localhost:8096');
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals(8096, $request->getPort());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getPath
     */
    public function testPathGetterReturnsComposedUriGetPathResult() {
        $uri = new Uri('http://localhost/test.php?var1=one&var2=2');
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('/test.php', $request->getPath());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getQuery
     */
    public function testQueryGetterReturnsComposedUriGetQueryResult() {
        $uri = new Uri('http://localhost/test.php?var1=one&var2=2');
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('var1=one&var2=2', $request->getQuery());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getFragment
     */
    public function testFragmentGetterReturnsComposedUriGetFragmentResult() {
        $uri = new Uri('http://localhost/test.php#someFrag');
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('someFrag', $request->getFragment());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getMethod
     */
    public function testMethodGetterReturnsMethodProperty() {
        $uri = new Uri('http://localhost');
        $request = new StdRequest($uri, 'DELETE', array());
        $this->assertEquals('DELETE', $request->getMethod());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getAuthority
     */
    public function testAuthorityGetterReturnsUriAuthority() {
        $uri = $this->getMock('Artax\\Uri', array('getAuthority'), array('http://something'));
        $uri->expects($this->once())
            ->method('getAuthority')
            ->will($this->returnValue('test'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('test', $request->getAuthority());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getUserInfo
     */
    public function testUserInfoGetterReturnsUriFunctionResult() {
        $uri = new Uri('http://user:pass@localhost');
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('user:********', $request->getUserInfo());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::hasQueryParameter
     * @covers Artax\Http\StdRequest::parseParametersFromString
     */
    public function testHasQueryParameterReturnsBoolOnParameterAvailability() {
        $uri = new Uri('http://localhost/test?var1=one&var2=2');
        $request = new StdRequest($uri, 'GET');
        $this->assertTrue($request->hasQueryParameter('var1'));
        $this->assertFalse($request->hasQueryParameter('var9999'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getQueryParameter
     * @covers Artax\Http\StdRequest::parseParametersFromString
     */
    public function testQueryParameterGetterReturnsRequestedParameterValue() {
        $uri = new Uri('http://localhost/test?var1=one&var2=2');
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('one', $request->getQueryParameter('var1'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getQueryParameter
     * @expectedException Spl\DomainException
     */
    public function testQueryParameterGetterThrowsExceptionOnInvalidParameterRequest() {
        $uri = new Uri('http://localhost/test?var1=one&var2=2');
        $request = new StdRequest($uri, 'GET');
        $request->getQueryParameter('var99999');
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getAllQueryParameters
     */
    public function testGetAllQueryParametersReturnsQueryParameterArray() {
        $uri = new Uri('http://localhost/test?var1=one&var2=2');
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals(array('var1'=>'one', 'var2'=>'2'), $request->getAllQueryParameters());
    }
    
    /**
     * @covers Artax\Http\StdRequest::setBody
     * @expectedException Artax\Http\HttpException
     */
    public function testSetBodyThrowsExceptionIfRequestMethodDoesntAllowEntityBody() {
        $uri = new Uri('http://localhost');
        $request = new StdRequest($uri, 'GET');
        $request->setBody('test');
    }
    
    public function provideMethodVerbsThatDontAllowEntityBody() {
        return array(
            array('GET'),
            array('HEAD'),
            array('DELETE'),
            array('TRACE'),
            array('CONNECT')
        );
    }
    
    /**
     * @dataProvider provideMethodVerbsThatDontAllowEntityBody
     * @covers Artax\Http\StdRequest::allowsEntityBody
     */
    public function testAllowsEntityBodyReturnsFalseForDisallowedMethods($method) {
        $uri = new Uri('http://localhost');
        $request = new StdRequest($uri, $method);
        $this->assertFalse($request->allowsEntityBody());
    }
    
    /**
     * @covers Artax\Http\StdRequest::allowsEntityBody
     */
    public function testAllowsEntityBodyReturnsTrueForAllowedMethods() {
        $uri = new Uri('http://localhost');
        $request = new StdRequest($uri, 'POST');
        $this->assertTrue($request->allowsEntityBody());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__toString
     */
    public function testToStringReturnsRawHttpMessage() {
        $uri = new Uri('http://localhost/someUrl?someVar=42');
        $request = new StdRequest($uri, 'POST');
        $request->setHeader('Host', 'localhost');
        $request->setHeader('Content-Type', 'test');
        $request->setHeader('Content-Length', 11);
        $request->setBody('entity body');
        
        $expected = "POST /someUrl?someVar=42 HTTP/1.1\r\n" .
                    "Host: localhost\r\n" .
                    "Content-Type: test\r\n" . 
                    "Content-Length: 11\r\n\r\n" .
                    "entity body";
        $this->assertEquals($expected, $request->__toString());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__toString
     * @covers Artax\Http\StdRequest::buildConnectMessage
     */
    public function testToStringConnectOutput() {
        $uri = new Uri('http://localhost:8096');
        $request = new StdRequest($uri, 'CONNECT');
        $request->setHeader('Content-Type', 'test');
        
        $expected = "CONNECT localhost:8096 HTTP/1.1\r\nContent-Type: test\r\n\r\n";
        $this->assertEquals($expected, $request->__toString());
    }
    
    /**
     * @covers Artax\Http\StdRequest::getProxyRequestLine
     */
    public function testProxyRequestLineGetterUsesAbsoluteUri() {
        $uri = new Uri('http://localhost:8096/test.html');
        $request = new StdRequest($uri, 'GET');
        $expected = "GET " . $uri . " HTTP/1.1";
        $this->assertEquals($expected, $request->getProxyRequestLine());
    }
    
    /**
     * @covers Artax\Http\StdRequest::getBody
     */
    public function testGetBodyReturnsAssignedBodyIfNotAResourceStream() {
        $uri = new Uri('http://localhost');
        $request = new StdRequest($uri, 'PUT');
        $request->setBody('request body');
        $this->assertEquals('request body', $request->getBody());
    }
    
    /**
     * @covers Artax\Http\StdRequest::getBodyStream
     */
    public function testGetStreamBodyReturnsNullIfBodyIsNotAResourceStream() {
        $uri = new Uri('http://localhost');
        $request = new StdRequest($uri, 'PUT');
        $request->setBody('requestBody');
        $this->assertNull($request->getBodyStream());
    }
    
    /**
     * @covers Artax\Http\StdRequest::getBodyStream
     */
    public function testGetStreamBodyCopiesUnseekablePhpInputStreamOnFirstAccess() {
        $uri = new Uri('http://localhost');
        $phpInput = fopen('php://input', 'r');
        $request = new StdRequest($uri, 'PUT');
        $request->setBody($phpInput);
        $this->assertEquals('', stream_get_contents($request->getBodyStream()));
    }
    
    /**
     * @covers Artax\Http\StdRequest::getBody
     */
    public function testGetBodyBuffersAndCopiesPhpInputStreamBody() {
        $uri = new Uri('http://localhost');
        $phpInput = fopen('php://input', 'r');
        $request = new StdRequest($uri, 'PUT');
        $request->setBody($phpInput);
        $this->assertEquals('', $request->getBody());
        $this->assertEquals('', stream_get_contents($request->getBodyStream()));
    }
    
    /**
     * @covers Artax\Http\StdRequest::getBody
     */
    public function testGetBodyBuffersStreamBodyOnFirstRead() {
        $uri = new Uri('http://localhost');
        $body = fopen('php://memory', 'r+');
        fwrite($body, 'test');
        rewind($body);
        
        $request = new StdRequest($uri, 'PUT');
        $request->setBody($body);
        $this->assertEquals('test', $request->getBody());
        $this->assertEquals('test', stream_get_contents($request->getBodyStream()));
        
        $this->assertEquals('test', $request->getBody());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::setBody
     */
    public function testSetBodyAssignmentReturnsNull() {
        $uri = new Uri('http://localhost');
        $request = new StdRequest($uri, 'POST');
        $this->assertNull($request->setBody('We few, we happy few.'));
        $this->assertEquals('We few, we happy few.', $request->getBody());
    }
    
    public function provideInvalidRawHeaders() {
        return array(
            array('Balderdash'),
            array('X-Requested-By'),
            array("Content-Type: text/html\r\nContent-Length: 42"),
            array("Vary: Accept,Accept-Charset,\r\nAccept-Encoding")
        );
    }
    
    /**
     * @covers Artax\Http\StdRequest::getRequestLine
     */
    public function testGetRequestLineReturnsExpectedValues() {
        $uri = new Uri('http://localhost/some-url?myVar=42');
        $request = new StdRequest($uri, 'GET');
        $request->setHttpVersion('1.0');
        $expected = 'GET /some-url?myVar=42 HTTP/1.0';
        $this->assertEquals($expected, $request->getRequestLine());
    }
}