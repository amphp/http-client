<?php

use Artax\Uri,
    Artax\Http\ValueRequest;

class ValueRequestTest extends PHPUnit_Framework_TestCase {
    
    public function provideInvalidMethodVerbs() {
        return array(
            array("GE\rT"),
            array("GE T"),
            array("POS\tT")
        );
    }
    
    /**
     * @dataProvider provideInvalidMethodVerbs
     * @expectedException Spl\DomainException
     */
    public function testConstructorThrowsExceptionOnInvalidMethodVerb($badMethod) {
        $request = new ValueRequest($badMethod, 'http://localhost', '1.1');
    }
    
    public function provideInvalidProtocols() {
        return array(
            array('11'),
            array(2),
            array(null),
            array('')
        );
    }
    
    /**
     * @dataProvider provideInvalidProtocols
     * @expectedException Spl\DomainException
     */
    public function testConstructorThrowsExceptionOnInvalidProtocol($badProtocol) {
        $request = new ValueRequest('GET', 'http://localhost', $badProtocol);
    }
    
    public function provideRequestsForRequestLineComparisons() {
        $return = array();
        
        // 0 --------------------------------------------------------------------------->
        $request = new ValueRequest('GET', 'http://localhost/some-url?myVar=42', '1.0');
        $expectedStartLine = 'GET /some-url?myVar=42 HTTP/1.0';
        $return[] = array($request, $expectedStartLine);
        
        // 1 --------------------------------------------------------------------------->
        $request = new ValueRequest('CONNECT', 'http://localhost:8096', '1.1');
        $expectedStartLine = 'CONNECT localhost:8096 HTTP/1.1';
        $return[] = array($request, $expectedStartLine);
        
        // x --------------------------------------------------------------------------->
        
        return $return;
    }
    
    /**
     * @dataProvider provideRequestsForRequestLineComparisons
     * @covers Artax\Http\ValueRequest::getStartLine
     */
    public function testGetStartLine($request, $expectedRequestLine) {
        $this->assertEquals($expectedRequestLine, $request->getStartLine());
    }
    
    /**
     * @covers Artax\Http\ValueRequest::__construct
     * @covers Artax\Http\ValueRequest::getMethod
     */
    public function testMethodGetterReturnsMethodProperty() {
        $request = new ValueRequest('DELETE', 'http://localhost', '1.1');
        $this->assertEquals('DELETE', $request->getMethod());
    }
    
    /**
     * @covers Artax\Http\ValueRequest::__construct
     * @covers Artax\Http\ValueRequest::getUri
     */
    public function testUriGetterReturnsUriToStringResult() {
        $request = new ValueRequest('GET', 'http://something', '1.1');
        $this->assertEquals('http://something', $request->getUri());
    }
    
    /**
     * @covers Artax\Http\ValueRequest::__construct
     * @covers Artax\Http\ValueRequest::hasQueryParameter
     */
    public function testHasQueryParameterReturnsBoolOnParameterAvailability() {
        $request = new ValueRequest('GET', 'http://localhost/test?var1=42&var2=0', '1.1');
        $this->assertTrue($request->hasQueryParameter('var1'));
        $this->assertFalse($request->hasQueryParameter('doesntExist'));
    }
    
    /**
     * @covers Artax\Http\ValueRequest::__construct
     * @covers Artax\Http\ValueRequest::getQueryParameter
     */
    public function testQueryParameterGetterReturnsRequestedParameterValue() {
        $request = new ValueRequest('GET', 'http://localhost/test?var1=one&var2=2', '1.1');
        $this->assertEquals('one', $request->getQueryParameter('var1'));
    }
    
    /**
     * @covers Artax\Http\ValueRequest::__construct
     * @covers Artax\Http\ValueRequest::getQueryParameter
     * @expectedException Spl\KeyException
     */
    public function testQueryParameterGetterThrowsExceptionOnInvalidParameterRequest() {
        $request = new ValueRequest('GET', 'http://localhost/test?var1=one&var2=2', '1.1');
        $request->getQueryParameter('var99999');
    }
    
    /**
     * @covers Artax\Http\ValueRequest::__construct
     * @covers Artax\Http\ValueRequest::getAllQueryParameters
     */
    public function testGetAllQueryParametersReturnsQueryParameterArray() {
        $request = new ValueRequest('GET', 'http://localhost/test?var1=one&var2=2', '1.1');
        $this->assertEquals(array('var1'=>'one', 'var2'=>'2'), $request->getAllQueryParameters());
    }
    
    public function testThatNoQueryParamsAreParsedOnEmptyUriQueryString() {
        $request = new ValueRequest('GET', 'http://localhost', '1.1', array());
        $this->assertEquals(array(), $request->getAllQueryParameters());
    }
    
    /**
     * @covers Artax\Http\ValueRequest::__construct
     * @covers Artax\Http\ValueRequest::__toString
     */
    public function testToStringReturnsRawHttpMessage() {
        $uri = 'http://localhost/someUrl?someVar=42';
        $headers = array(
            'Host' => 'localhost',
            'Content-Type' => 'test',
            'Content-Length' => 11
        );
        $body = 'entity body';
        
        $request = new ValueRequest('POST', $uri, '1.1', $headers, $body);
        $expected = "POST /someUrl?someVar=42 HTTP/1.1\r\n" .
                    "Host: localhost\r\n" .
                    "Content-Type: test\r\n" . 
                    "Content-Length: 11\r\n" .
                    "\r\n" .
                    $body;
        
        $this->assertEquals($expected, $request->__toString());
    }
    
    /**
     * @covers Artax\Http\ValueRequest::__toString
     */
    public function testToStringConnectOutput() {
        $headers = array('Content-Type' => 'test');
        $request = new ValueRequest('CONNECT', 'http://localhost:8096', '1.1', $headers);
        
        $expected = "CONNECT localhost:8096 HTTP/1.1\r\n" .
                    "Content-Type: test\r\n\r\n";
        
        $this->assertEquals($expected, $request->__toString());
    }
    
    /**
     * @covers Artax\Http\ValueRequest::getBody
     */
    public function testGetBodyReturnsAssignedBody() {
        $request = new ValueRequest('PUT', 'http://localhost', '1.1', array(), 'request body');
        $this->assertEquals('request body', $request->getBody());
    }
}