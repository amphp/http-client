<?php

use Artax\Http\FormEncodedRequest,
    Artax\Http\StdUri;

class FormEncodedRequestTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\FormEncodedRequest::__construct
     * @covers Artax\Http\FormEncodedRequest::hasFormEncodedBody
     * @covers Artax\Http\FormEncodedRequest::parseBodyParameters
     * @covers Artax\Http\FormEncodedRequest::acceptsEntityBody
     */
    public function testConstructorAssignsFormEncodedBodyParametersIfDefined() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new FormEncodedRequest($uri, 'POST', $headers, $body);
        
        $this->assertEquals('one', $request->getBodyParameter('var1'));
        $this->assertEquals('two', $request->getBodyParameter('var2'));
        $this->assertEquals('Yes sir', $request->getBodyParameter('var3'));
        
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = fopen('php://memory', 'w+');
        fwrite($body, 'var4=four&var5=five&var6=six');
        rewind($body);
        $request = new FormEncodedRequest($uri, 'POST', $headers, $body);
        
        $this->assertEquals('four', $request->getBodyParameter('var4'));
        $this->assertEquals('five', $request->getBodyParameter('var5'));
        $this->assertEquals('six', $request->getBodyParameter('var6'));
        
        $request = new FormEncodedRequest($uri, 'POST', array(), 'test');
        $this->assertEquals(array(), $request->getAllBodyParameters());
    }
    
    /**
     * @covers Artax\Http\FormEncodedRequest::__construct
     * @covers Artax\Http\FormEncodedRequest::getAllBodyParameters
     */
    public function testGetAllBodyParametersReturnsArrayOfBodyParameters() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new FormEncodedRequest($uri, 'POST', $headers, $body);
        
        $expected = array('var1' => 'one', 'var2' => 'two', 'var3' => 'Yes sir');
        $this->assertEquals($expected, $request->getAllBodyParameters());
        
        $request = new FormEncodedRequest($uri, 'POST', array());
        $this->assertEquals(array(), $request->getAllBodyParameters());
    }
    
    /**
     * @covers Artax\Http\FormEncodedRequest::__construct
     * @covers Artax\Http\FormEncodedRequest::hasBodyParameter
     */
    public function testHasBodyParameterReturnsBoolOnParameterAvailability() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new FormEncodedRequest($uri, 'POST', $headers, $body);
        
        $this->assertTrue($request->hasBodyParameter('var1'));
        $this->assertFalse($request->hasBodyParameter('var999'));
    }
    
    /**
     * @covers Artax\Http\FormEncodedRequest::__construct
     * @covers Artax\Http\FormEncodedRequest::getBodyParameter
     * @covers Artax\Http\FormEncodedRequest::assignHeader
     * @covers Artax\Http\FormEncodedRequest::assignAllHeaders
     */
    public function testGetBodyParameterReturnsRequestParameterValue() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new FormEncodedRequest($uri, 'POST', $headers, $body);
        
        $this->assertEquals('one', $request->getBodyParameter('var1'));
    }
    
    /**
     * @covers Artax\Http\FormEncodedRequest::__construct
     * @covers Artax\Http\FormEncodedRequest::getBodyParameter
     * @covers Artax\Http\FormEncodedRequest::assignHeader
     * @covers Artax\Http\FormEncodedRequest::assignAllHeaders
     * @expectedException RuntimeException
     */
    public function testGetBodyParameterExceptionOnInvalidParameter() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new FormEncodedRequest($uri, 'POST', $headers, $body);
        
        $request->getBodyParameter('var999999');
    }
}
