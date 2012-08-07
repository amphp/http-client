<?php

use Artax\Http\StdRequest,
    Artax\Http\StdUri;

class StdRequestTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     */
    public function testConstructorAssignsProperties() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $request = new StdRequest($uri, 'PUT', array(), 'request body', '1.1');
        $this->assertInstanceOf('Artax\\Http\\StdRequest', $request);
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::buildUriFromString
     */
    public function testConstructorBuildsUriInstanceOnStringParameter() {
        $uri = 'http://www.google.com/';
        $request = new StdRequest($uri, 'PUT', array(), 'request body');
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
        $request = new StdRequest($uri, 'PUT', array(), 'request body');
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::normalizeHeaders
     */
    public function testNormalizeHeadersUppercasesHeaderFieldNames() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $headers = array('Accept-Charset' => '*/*');
        $request = new StdRequest($uri, 'PUT', $headers, 'request body');
        $this->assertTrue($request->hasHeader('ACCEPT-CHARSET'));
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
        
        $request = new StdRequest($uri, 'GET', array());
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
        
        $request = new StdRequest($uri, 'GET', array());
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
        
        $request = new StdRequest($uri, 'GET', array());
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
        
        $request = new StdRequest($uri, 'GET', array());
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
        
        $request = new StdRequest($uri, 'GET', array());
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
        
        $request = new StdRequest($uri, 'GET', array());
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
        
        $request = new StdRequest($uri, 'GET', array());
        $this->assertEquals('idSomething', $request->getFragment());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getMethod
     */
    public function testMethodGetterReturnsMethodProperty() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $request = new StdRequest($uri, 'delete', array());
        $this->assertEquals('DELETE', $request->getMethod());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     */
    public function testNormalizeMethodUppercasesMethodArg() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $request = new StdRequest($uri, 'delete', array());
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
        
        $request = new StdRequest($uri, 'GET', array());
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
        
        $request = new StdRequest($uri, 'GET', array());
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
        
        $request = new StdRequest($uri, 'GET', array());
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
        
        $request = new StdRequest($uri, 'GET', array());
        $this->assertEquals(array('var1'=>'one', 'var2'=>'2'), $request->getAllQueryParameters());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::hasFormEncodedBody
     * @covers Artax\Http\StdRequest::parseParametersFromString
     * @covers Artax\Http\StdRequest::acceptsEntityBody
     */
    public function testConstructorAssignsFormEncodedBodyParametersIfDefined() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new StdRequest($uri, 'POST', $headers, $body);
        
        $this->assertEquals('one', $request->getBodyParameter('var1'));
        $this->assertEquals('two', $request->getBodyParameter('var2'));
        $this->assertEquals('Yes sir', $request->getBodyParameter('var3'));
        
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = fopen('php://memory', 'w+');
        fwrite($body, 'var4=four&var5=five&var6=six');
        rewind($body);
        $request = new StdRequest($uri, 'POST', $headers, $body);
        
        $this->assertEquals('four', $request->getBodyParameter('var4'));
        $this->assertEquals('five', $request->getBodyParameter('var5'));
        $this->assertEquals('six', $request->getBodyParameter('var6'));
        
        $request = new StdRequest($uri, 'POST', array(), 'test');
        $this->assertEquals(array(), $request->getAllBodyParameters());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getAllBodyParameters
     */
    public function testGetAllBodyParametersReturnsArrayOfBodyParameters() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new StdRequest($uri, 'POST', $headers, $body);
        
        $expected = array('var1' => 'one', 'var2' => 'two', 'var3' => 'Yes sir');
        $this->assertEquals($expected, $request->getAllBodyParameters());
        
        $request = new StdRequest($uri, 'POST', array());
        $this->assertEquals(array(), $request->getAllBodyParameters());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::hasBodyParameter
     */
    public function testHasBodyParameterReturnsBoolOnParameterAvailability() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new StdRequest($uri, 'POST', $headers, $body);
        
        $this->assertTrue($request->hasBodyParameter('var1'));
        $this->assertFalse($request->hasBodyParameter('var999'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getBodyParameter
     * @covers Artax\Http\StdRequest::assignHeader
     * @covers Artax\Http\StdRequest::assignAllHeaders
     */
    public function testGetBodyParameterReturnsRequestParameterValue() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new StdRequest($uri, 'POST', $headers, $body);
        
        $this->assertEquals('one', $request->getBodyParameter('var1'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getBodyParameter
     * @covers Artax\Http\StdRequest::assignHeader
     * @covers Artax\Http\StdRequest::assignAllHeaders
     * @expectedException RuntimeException
     */
    public function testGetBodyParameterExceptionOnInvalidParameter() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new StdRequest($uri, 'POST', $headers, $body);
        
        $request->getBodyParameter('var999999');
    }
    
    /**
     * @covers Artax\Http\StdRequest::__toString
     * @covers Artax\Http\StdRequest::buildConnectMessage
     */
    public function testToStringConnectOutput() {
        $uri = new StdUri('http://localhost:8096');
        $headers = array('Content-Type' => 'test');
        $request = new StdRequest($uri, 'CONNECT', $headers);
        
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
        $request = new StdRequest($uri, 'POST', $headers, $body);
        
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
        
        $expected = "GET " . $uri . " HTTP/1.1\r\n";
        
        $this->assertEquals($expected, $request->getProxyRequestLine());
    }
}
