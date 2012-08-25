<?php

use Artax\Http\FormEncodableRequest,
    Artax\Http\StdUri;

class FormEncodableRequestTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\FormEncodableRequest::__construct
     * @covers Artax\Http\FormEncodableRequest::getAllBodyParameters
     */
    public function testGetAllBodyParametersReturnsArrayOfBodyParameters() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new FormEncodableRequest($uri, 'POST');
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->setBody($body);
        
        $expected = array('var1' => 'one', 'var2' => 'two', 'var3' => 'Yes sir');
        $this->assertEquals($expected, $request->getAllBodyParameters());
        
        $request = new FormEncodableRequest($uri, 'POST', array());
        $this->assertEquals(array(), $request->getAllBodyParameters());
    }
    
    /**
     * @covers Artax\Http\FormEncodableRequest::__construct
     * @covers Artax\Http\FormEncodableRequest::hasBodyParameter
     */
    public function testHasBodyParameterReturnsBoolOnParameterAvailability() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new FormEncodableRequest($uri, 'POST');
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->setBody($body);
        
        $this->assertTrue($request->hasBodyParameter('var1'));
        $this->assertFalse($request->hasBodyParameter('var999'));
    }
    
    /**
     * @covers Artax\Http\FormEncodableRequest::__construct
     * @covers Artax\Http\FormEncodableRequest::getBodyParameter
     */
    public function testGetBodyParameterReturnsRequestParameterValue() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new FormEncodableRequest($uri, 'POST');
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->setBody($body);
        
        $this->assertEquals('one', $request->getBodyParameter('var1'));
    }
    
    /**
     * @covers Artax\Http\FormEncodableRequest::__construct
     * @covers Artax\Http\FormEncodableRequest::getBodyParameter
     * @expectedException RuntimeException
     */
    public function testGetBodyParameterExceptionOnInvalidParameter() {
        $uri = $this->getMock('Artax\\Http\\Uri');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new FormEncodableRequest($uri, 'POST');
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->setBody($body);
        
        $request->getBodyParameter('var999999');
    }
}
