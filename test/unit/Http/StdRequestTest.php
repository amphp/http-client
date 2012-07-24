<?php

use Artax\Http\StdRequest;

class StdRequestTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     */
    public function testConstructorAssignsProperties() {
        $uri = $this->getMock('Artax\\Uri');
        $request = new StdRequest($uri, '1.1', 'PUT', array(), 'request body');
        $this->assertInstanceOf('Artax\\Http\\StdRequest', $request);
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::buildUri
     */
    public function testConstructorBuildsUriInstanceOnStringParameter() {
        $uri = 'http://www.google.com/';
        $request = new StdRequest($uri, '1.1', 'PUT', array(), 'request body');
        $this->assertInstanceOf('Artax\\Http\\StdRequest', $request);
        $this->assertEquals($uri, $request->getUri());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::buildUri
     * @expectedException InvalidArgumentException
     */
    public function testConstructorThrowsExceptionOnInvalidUriString() {
        $uri = 'http://';
        $request = new StdRequest($uri, '1.1', 'PUT', array(), 'request body');
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::normalizeHeaders
     */
    public function testNormalizeHeadersUppercasesHeaderFieldNames() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Accept-Charset' => '*/*');
        $request = new StdRequest($uri, '1.1', 'PUT', $headers, 'request body');
        $this->assertTrue($request->hasHeader('ACCEPT-CHARSET'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getHttpVersion
     */
    public function testHttpVersionGetterReturnsPropertyValue() {
        $uri = $this->getMock('Artax\\Uri');
        $request = new StdRequest($uri, '1.1', 'GET', array());
        $this->assertInstanceOf('Artax\\Http\\StdRequest', $request);
        $this->assertEquals('1.1', $request->getHttpVersion());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getUri
     */
    public function testUriGetterReturnsComposedUriToStringResult() {
        $uri = $this->getMock('Artax\\Uri');
        $uri->expects($this->once())
            ->method('__toString')
            ->will($this->returnValue('test'));
        
        $request = new StdRequest($uri, '1.1', 'GET', array());
        $this->assertEquals('test', $request->getUri());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getScheme
     */
    public function testSchemeGetterReturnsComposedUriGetSchemeResult() {
        $uri = $this->getMock('Artax\\Uri');
        $uri->expects($this->once())
            ->method('getScheme')
            ->will($this->returnValue('https'));
        
        $request = new StdRequest($uri, '1.1', 'GET', array());
        $this->assertEquals('https', $request->getScheme());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getHost
     */
    public function testHostGetterReturnsComposedUriGetHostResult() {
        $uri = $this->getMock('Artax\\Uri');
        $uri->expects($this->once())
            ->method('getHost')
            ->will($this->returnValue('localhost'));
        
        $request = new StdRequest($uri, '1.1', 'GET', array());
        $this->assertEquals('localhost', $request->getHost());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getPort
     */
    public function testPortGetterReturnsComposedUriGetPortResult() {
        $uri = $this->getMock('Artax\\Uri');
        $uri->expects($this->once())
            ->method('getPort')
            ->will($this->returnValue('80'));
        
        $request = new StdRequest($uri, '1.1', 'GET', array());
        $this->assertEquals('80', $request->getPort());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getPath
     */
    public function testPathGetterReturnsComposedUriGetPathResult() {
        $uri = $this->getMock('Artax\\Uri');
        $uri->expects($this->once())
            ->method('getPath')
            ->will($this->returnValue('/index.html'));
        
        $request = new StdRequest($uri, '1.1', 'GET', array());
        $this->assertEquals('/index.html', $request->getPath());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getQuery
     */
    public function testQueryGetterReturnsComposedUriGetQueryResult() {
        $uri = $this->getMock('Artax\\Uri');
        $uri->expects($this->any())
            ->method('getQuery')
            ->will($this->returnValue('var1=one&var2=2'));
        
        $request = new StdRequest($uri, '1.1', 'GET', array());
        $this->assertEquals('var1=one&var2=2', $request->getQuery());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getFragment
     */
    public function testFragmentGetterReturnsComposedUriGetFragmentResult() {
        $uri = $this->getMock('Artax\\Uri');
        $uri->expects($this->any())
            ->method('getFragment')
            ->will($this->returnValue('idSomething'));
        
        $request = new StdRequest($uri, '1.1', 'GET', array());
        $this->assertEquals('idSomething', $request->getFragment());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getMethod
     */
    public function testMethodGetterReturnsMethodProperty() {
        $uri = $this->getMock('Artax\\Uri');
        $request = new StdRequest($uri, '1.1', 'delete', array());
        $this->assertEquals('DELETE', $request->getMethod());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     */
    public function testNormalizeMethodUppercasesMethodArg() {
        $uri = $this->getMock('Artax\\Uri');
        $request = new StdRequest($uri, '1.1', 'delete', array());
        $this->assertEquals('DELETE', $request->getMethod());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::hasQueryParameter
     */
    public function testHasQueryParameterReturnsBoolOnParameterAvailability() {
        $uri = $this->getMock('Artax\\Uri');
        $uri->expects($this->any())
            ->method('getQuery')
            ->will($this->returnValue('var1=one&var2=2'));
        
        $request = new StdRequest($uri, '1.1', 'GET', array());
        $this->assertTrue($request->hasQueryParameter('var1'));
        $this->assertFalse($request->hasQueryParameter('var9999'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getQueryParameter
     */
    public function testQueryParameterGetterReturnsRequestedParameterValue() {
        $uri = $this->getMock('Artax\\Uri');
        $uri->expects($this->any())
            ->method('getQuery')
            ->will($this->returnValue('var1=one&var2=2'));
        
        $request = new StdRequest($uri, '1.1', 'GET', array());
        $this->assertEquals('one', $request->getQueryParameter('var1'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getQueryParameter
     * @expectedException RuntimeException
     */
    public function testQueryParameterGetterThrowsExceptionOnInvalidParameterRequest() {
        $uri = $this->getMock('Artax\\Uri');
        $uri->expects($this->any())
            ->method('getQuery')
            ->will($this->returnValue('var1=one&var2=2'));
        
        $request = new StdRequest($uri, '1.1', 'GET', array());
        $request->getQueryParameter('var99999');
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getAllQueryParameters
     */
    public function testGetAllQueryParametersReturnsQueryParameterArray() {
        $uri = $this->getMock('Artax\\Uri');
        $uri->expects($this->any())
            ->method('getQuery')
            ->will($this->returnValue('var1=one&var2=2'));
        
        $request = new StdRequest($uri, '1.1', 'GET', array());
        $this->assertEquals(array('var1'=>'one', 'var2'=>'2'), $request->getAllQueryParameters());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::hasHeader
     */
    public function testHasHeaderReturnsBoolOnDefinedHeaderParameter() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $request = new StdRequest($uri, '1.1', 'POST', $headers, 'var1=one&var2=two');
        
        $this->assertTrue($request->hasHeader('Content-Type'));
        $this->assertFalse($request->hasHeader('DoesntExist'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getHeader
     */
    public function testGetHeaderReturnsHeaderParameterIfAvailable() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $request = new StdRequest($uri, '1.1', 'POST', $headers, 'var1=one&var2=two');
        
        $this->assertEquals('application/x-www-form-urlencoded',
            $request->getHeader('Content-Type')
        );
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getHeader
     * @expectedException RuntimeException
     */
    public function testGetHeaderThrowsExceptionOnInvalidHeaderName() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $request = new StdRequest($uri, '1.1', 'POST', $headers, 'var1=one&var2=two');
        $request->getHeader('Invalid-Header');
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getAllHeaders
     */
    public function testGetAllHeadersReturnsHeaderArray() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $request = new StdRequest($uri, '1.1', 'POST', $headers, 'var1=one&var2=two');
        
        $this->assertEquals(array('CONTENT-TYPE' => 'application/x-www-form-urlencoded'),
            $request->getAllHeaders()
        );
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getBody
     */
    public function testGetBodyReturnsProperty() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $request = new StdRequest($uri, '1.1', 'POST', $headers, 'var1=one&var2=two');
        
        $this->assertEquals('var1=one&var2=two', $request->getBody());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::hasFormEncodedBody
     * @covers Artax\Http\StdRequest::parseParametersFromString
     * @covers Artax\Http\StdRequest::acceptsEntityBody
     */
    public function testConstructorAssignsFormEncodedBodyParametersIfDefined() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new StdRequest($uri, '1.1', 'POST', $headers, $body);
        
        $this->assertEquals('one', $request->getBodyParameter('var1'));
        $this->assertEquals('two', $request->getBodyParameter('var2'));
        $this->assertEquals('Yes sir', $request->getBodyParameter('var3'));
        
        $request = new StdRequest($uri, '1.1', 'POST', array());
        $this->assertEquals(array(), $request->getAllBodyParameters());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getAllBodyParameters
     */
    public function testGetAllBodyParametersReturnsArrayOfBodyParameters() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new StdRequest($uri, '1.1', 'POST', $headers, $body);
        
        $expected = array('var1' => 'one', 'var2' => 'two', 'var3' => 'Yes sir');
        $this->assertEquals($expected, $request->getAllBodyParameters());
        
        $request = new StdRequest($uri, '1.1', 'POST', array());
        $this->assertEquals(array(), $request->getAllBodyParameters());
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::hasBodyParameter
     */
    public function testHasBodyParameterReturnsBoolOnParameterAvailability() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new StdRequest($uri, '1.1', 'POST', $headers, $body);
        
        $this->assertTrue($request->hasBodyParameter('var1'));
        $this->assertFalse($request->hasBodyParameter('var999'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getBodyParameter
     */
    public function testGetBodyParameterReturnsRequestParameterValue() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new StdRequest($uri, '1.1', 'POST', $headers, $body);
        
        $this->assertEquals('one', $request->getBodyParameter('var1'));
    }
    
    /**
     * @covers Artax\Http\StdRequest::__construct
     * @covers Artax\Http\StdRequest::getBodyParameter
     * @expectedException RuntimeException
     */
    public function testGetBodyParameterExceptionOnInvalidParameter() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new StdRequest($uri, '1.1', 'POST', $headers, $body);
        
        $request->getBodyParameter('var999999');
    }
}
