<?php

use Artax\Http\ParameterizedRequest;

class ParameterizedRequestTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\ParameterizedRequest::__construct
     * @covers Artax\Http\ParameterizedRequest::hasFormEncodedBody
     * @covers Artax\Http\ParameterizedRequest::parseParametersFromString
     * @covers Artax\Http\ParameterizedRequest::acceptsEntityBody
     */
    public function testConstructorAssignsFormEncodedBodyParametersIfDefined() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new ParameterizedRequest($uri, '1.1', 'POST', $headers, $body);
        
        $this->assertEquals('one', $request->getBodyParameter('var1'));
        $this->assertEquals('two', $request->getBodyParameter('var2'));
        $this->assertEquals('Yes sir', $request->getBodyParameter('var3'));
        
        $request = new ParameterizedRequest($uri, '1.1', 'POST', array());
        $this->assertEquals(array(), $request->getAllBodyParameters());
    }
    
    /**
     * @covers Artax\Http\ParameterizedRequest::__construct
     * @covers Artax\Http\ParameterizedRequest::getAllBodyParameters
     */
    public function testGetAllBodyParametersReturnsArrayOfBodyParameters() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new ParameterizedRequest($uri, '1.1', 'POST', $headers, $body);
        
        $expected = array('var1' => 'one', 'var2' => 'two', 'var3' => 'Yes sir');
        $this->assertEquals($expected, $request->getAllBodyParameters());
        
        $request = new ParameterizedRequest($uri, '1.1', 'POST', array());
        $this->assertEquals(array(), $request->getAllBodyParameters());
    }
    
    /**
     * @covers Artax\Http\ParameterizedRequest::__construct
     * @covers Artax\Http\ParameterizedRequest::hasBodyParameter
     */
    public function testHasBodyParameterReturnsBoolOnParameterAvailability() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new ParameterizedRequest($uri, '1.1', 'POST', $headers, $body);
        
        $this->assertTrue($request->hasBodyParameter('var1'));
        $this->assertFalse($request->hasBodyParameter('var999'));
    }
    
    /**
     * @covers Artax\Http\ParameterizedRequest::__construct
     * @covers Artax\Http\ParameterizedRequest::getBodyParameter
     */
    public function testGetBodyParameterReturnsRequestParameterValue() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new ParameterizedRequest($uri, '1.1', 'POST', $headers, $body);
        
        $this->assertEquals('one', $request->getBodyParameter('var1'));
    }
    
    /**
     * @covers Artax\Http\ParameterizedRequest::__construct
     * @covers Artax\Http\ParameterizedRequest::getBodyParameter
     * @expectedException RuntimeException
     */
    public function testGetBodyParameterExceptionOnInvalidParameter() {
        $uri = $this->getMock('Artax\\Uri');
        $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new ParameterizedRequest($uri, '1.1', 'POST', $headers, $body);
        
        $request->getBodyParameter('var999999');
    }
}
