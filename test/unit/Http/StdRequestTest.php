<?php

use Artax\Http\StdRequest,
    Artax\Http\StdUri;

class StdRequestTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::buildUriFromString
     * @covers Artax\Http\StdRequest::parseParametersFromString
     */
    public function testConstructorBuildsUriInstanceOnStringParameter() {
        $uri = 'http://www.google.com/';
        $request = new StdRequest($uri, 'PUT');
        $this->assertInstanceOf('Artax\\Http\\StdRequest', $request);
        $this->assertEquals($uri, $request->getUri());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::buildUriFromString
     * @expectedException InvalidArgumentException
     */
    public function testConstructorThrowsExceptionOnInvalidUriString() {
        $uri = 'http://';
        $request = new StdRequest($uri, 'PUT');
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getUri
     */
    public function testUriGetterReturnsComposedUriToStringResult() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->once())
            ->method('__toString')
            ->will($this->returnValue('test'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('test', $request->getUri());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getRawUri
     */
    public function testRawUriGetterReturnsProtectedUriString() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->once())
            ->method('getRawUri')
            ->will($this->returnValue('test'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('test', $request->getRawUri());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getScheme
     */
    public function testSchemeGetterReturnsComposedUriGetSchemeResult() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->once())
            ->method('getScheme')
            ->will($this->returnValue('https'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('https', $request->getScheme());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getHost
     */
    public function testHostGetterReturnsComposedUriGetHostResult() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->once())
            ->method('getHost')
            ->will($this->returnValue('localhost'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('localhost', $request->getHost());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getPort
     */
    public function testPortGetterReturnsComposedUriGetPortResult() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->once())
            ->method('getPort')
            ->will($this->returnValue('80'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('80', $request->getPort());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getPath
     */
    public function testPathGetterReturnsComposedUriGetPathResult() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->once())
            ->method('getPath')
            ->will($this->returnValue('/index.html'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('/index.html', $request->getPath());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getQuery
     */
    public function testQueryGetterReturnsComposedUriGetQueryResult() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->any())
            ->method('getQuery')
            ->will($this->returnValue('var1=one&var2=2'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('var1=one&var2=2', $request->getQuery());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getFragment
     */
    public function testFragmentGetterReturnsComposedUriGetFragmentResult() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->any())
            ->method('getFragment')
            ->will($this->returnValue('idSomething'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('idSomething', $request->getFragment());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getMethod
     */
    public function testMethodGetterReturnsMethodProperty() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $request = new StdRequest($uri, 'DELETE', array());
        $this->assertEquals('DELETE', $request->getMethod());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getAuthority
     */
    public function testAuthorityGetterReturnsUriFunctionResult() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->once())
            ->method('getAuthority')
            ->will($this->returnValue('test'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('test', $request->getAuthority());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getRawAuthority
     */
    public function testRawAuthorityGetterReturnsUriFunctionResult() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->once())
            ->method('getRawAuthority')
            ->will($this->returnValue('test'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('test', $request->getRawAuthority());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getUserInfo
     */
    public function testUserInfoGetterReturnsUriFunctionResult() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->once())
            ->method('getUserInfo')
            ->will($this->returnValue('test'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('test', $request->getUserInfo());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getRawUserInfo
     */
    public function testRawUserInfoGetterReturnsUriFunctionResult() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->once())
            ->method('getRawUserInfo')
            ->will($this->returnValue('test'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('test', $request->getRawUserInfo());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::hasQueryParameter
     */
    public function testHasQueryParameterReturnsBoolOnParameterAvailability() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->any())
            ->method('getQuery')
            ->will($this->returnValue('var1=one&var2=2'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertTrue($request->hasQueryParameter('var1'));
        $this->assertFalse($request->hasQueryParameter('var9999'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getQueryParameter
     */
    public function testQueryParameterGetterReturnsRequestedParameterValue() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->any())
            ->method('getQuery')
            ->will($this->returnValue('var1=one&var2=2'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals('one', $request->getQueryParameter('var1'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getQueryParameter
     * @expectedException RuntimeException
     */
    public function testQueryParameterGetterThrowsExceptionOnInvalidParameterRequest() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->any())
            ->method('getQuery')
            ->will($this->returnValue('var1=one&var2=2'));
        
        $request = new StdRequest($uri, 'GET');
        $request->getQueryParameter('var99999');
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getAllQueryParameters
     */
    public function testGetAllQueryParametersReturnsQueryParameterArray() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $uri->expects($this->any())
            ->method('getQuery')
            ->will($this->returnValue('var1=one&var2=2'));
        
        $request = new StdRequest($uri, 'GET');
        $this->assertEquals(array('var1'=>'one', 'var2'=>'2'), $request->getAllQueryParameters());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__toString
     * @covers Artax\Http\StdRequest::buildConnectMessage
     */
    public function testToStringConnectOutput() {
        $uri = new StdUri('http://localhost:8096');
        $request = new StdRequest($uri, 'CONNECT');
        $request->setHeader('Content-Type', 'test');
        
        $expected = "CONNECT localhost:8096 HTTP/1.1\r\nCONTENT-TYPE: test\r\n\r\n";
        $this->assertEquals($expected, $request->__toString());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__toString
     * @covers Artax\Http\StdRequest::getRequestLine
     */
    public function testToStringAutoAppliesHostFromUri() {
        $uri = new StdUri('http://user:pass@localhost:8096/test.html?var=42');
        $headers = array('Accept' => 'text/*', 'Host' => 'invalid');
        $body = 'We few, we happy few';
        $request = new StdRequest($uri, 'POST');
        $request->setAllHeaders($headers);
        $request->setBody($body);
        
        $expected = "POST /test.html?var=42 HTTP/1.1\r\n";
        $expected.= "HOST: user:********@localhost:8096\r\n";
        $expected.= "ACCEPT: text/*\r\n\r\n";
        $expected.= $body;
        
        $this->assertEquals($expected, $request->__toString());
    }
    
    /**
     * @covers Artax\Http\StdRequest::getProxyRequestLine
     */
    public function testProxyRequestLineGetterUsesAbsoluteUri() {
        $uri = new StdUri('http://localhost:8096/test.html');
        $request = new StdRequest($uri, 'GET');
        
        $expected = "GET " . $uri . " HTTP/1.1";
        
        $this->assertEquals($expected, $request->getProxyRequestLine());
    }
    
    /**
     * @covers Artax\Http\StdRequest::getBody
     */
    public function testGetBodyReturnsAssignedBodyIfNotAResourceStream() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $request = new StdRequest($uri, 'PUT');
        $request->setBody('request body');
        $this->assertEquals('request body', $request->getBody());
    }
    
    /**
     * @covers Artax\Http\StdRequest::getBodyStream
     */
    public function testGetStreamBodyReturnsNullIfBodyIsNotAResourceStream() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $request = new StdRequest($uri, 'PUT');
        $request->setBody('requestBody');
        $this->assertNull($request->getBodyStream());
    }
    
    /**
     * @covers Artax\Http\StdRequest::getBodyStream
     */
    public function testGetStreamBodyCopiesUnseekablePhpInputStreamOnFirstAccess() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $phpInput = fopen('php://input', 'r');
        $request = new StdRequest($uri, 'PUT');
        $request->setBody($phpInput);
        $this->assertEquals('', stream_get_contents($request->getBodyStream()));
    }
    
    /**
     * @covers Artax\Http\StdRequest::getBody
     */
    public function testGetBodyBuffersAndCopiesPhpInputStreamBody() {
        $uri = $this->getMock('Artax\\Http\\Uri');
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
        $uri = $this->getMock('Artax\\Http\\Uri');
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
     * @covers Artax\Http\StdRequest::setHttpVersion
     */
    public function testSetHttpVersionReturnsNull() {
        $request = new StdRequest('http://localhost', 'GET');
        $this->assertEquals('1.1', $request->getHttpVersion());
        $this->assertNull($request->setHttpVersion('1.0'));
        $this->assertEquals('1.0', $request->getHttpVersion());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::setHeader
     */
    public function testSetHeaderTrimsTrailingColonThenAssignsValueAndReturnsNull() {
        $request = new StdRequest('http://localhost', 'GET');
        $this->assertNull($request->setHeader('Accept:', 'text/*'));
        $this->assertEquals('text/*', $request->getHeader('accept'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::setHeader
     * @covers Artax\Http\StdRequest::setAllHeaders
     */
    public function testSetAllHeadersAssignsValuesAndReturnsNull() {
        $request = new StdRequest('http://localhost', 'GET');
        $this->assertNull($request->setAllHeaders(array('Accept'=>'text/*')));
        $this->assertEquals('text/*', $request->getHeader('accept'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::setHeader
     * @covers Artax\Http\StdRequest::setAllHeaders
     * @expectedException InvalidArgumentException
     */
    public function testSetAllHeadersThrowsExceptionOnInvalidIterable() {
        $request = new StdRequest('http://localhost', 'GET');
        $request->setAllHeaders('not iterable');
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::removeHeader
     */
    public function testRemoveHeaderDoesAndReturnsNull() {
        $request = new StdRequest('http://localhost', 'GET');
        $request->setHeader('Accept', 'text/*');
        $this->assertEquals('text/*', $request->getHeader('accept'));
        $this->assertNull($request->removeHeader('Accept'));
        $this->assertFalse($request->hasHeader('Accept'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::setBody
     */
    public function testSetBodyDoesAndReturnsNull() {
        $request = new StdRequest('http://localhost', 'POST');
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
}
