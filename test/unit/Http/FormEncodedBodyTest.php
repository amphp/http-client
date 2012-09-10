<?php

use Artax\Http\FormEncodedBody,
    Artax\Http\StdRequest,
    Artax\Uri;

class FormEncodedBodyTest extends PHPUnit_Framework_TestCase {

    /**
     * @covers Artax\Http\FormEncodedBody::__construct
     * @covers Artax\Http\FormEncodedBody::assignValuesFromEntityBody
     * @covers Artax\Http\FormEncodedBody::hasFormEncodedBody
     * @covers Artax\Http\FormEncodedBody::getAllBodyParameters
     */
    public function testGetAllBodyParametersReturnsArrayOfBodyParameters() {
        $uri = new Uri('http://localhost');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new StdRequest($uri, 'POST');
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->setBody($body);

        $formEncodedBody = new FormEncodedBody($request);
        $expected = array('var1' => 'one', 'var2' => 'two', 'var3' => 'Yes sir');
        $this->assertEquals($expected, $formEncodedBody->getAllBodyParameters());
    }
    
    /**
     * @covers Artax\Http\FormEncodedBody::__construct
     * @covers Artax\Http\FormEncodedBody::hasFormEncodedBody
     * @covers Artax\Http\FormEncodedBody::getAllBodyParameters
     */
    public function testParamsArrayIsEmptyIfNotAFormEncodedRequest() {
        $uri = new Uri('http://localhost');
        $request = new StdRequest($uri, 'POST');
        $request->setBody('some entity body value');
        $formEncodedBody = new FormEncodedBody($request);
        $this->assertEquals(array(), $formEncodedBody->getAllBodyParameters());
    }

    /**
     * @covers Artax\Http\FormEncodedBody::__construct
     * @covers Artax\Http\FormEncodedBody::hasBodyParameter
     */
    public function testHasBodyParameterReturnsBoolOnParameterAvailability() {
        $uri = new Uri('http://localhost');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new StdRequest($uri, 'POST');
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->setBody($body);

        $formEncodedBody = new FormEncodedBody($request);
        $this->assertTrue($formEncodedBody->hasBodyParameter('var1'));
        $this->assertFalse($formEncodedBody->hasBodyParameter('var999'));
    }

    /**
     * @covers Artax\Http\FormEncodedBody::__construct
     * @covers Artax\Http\FormEncodedBody::getBodyParameter
     */
    public function testGetBodyParameterReturnsRequestParameterValue() {
        $uri = new Uri('http://localhost');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new StdRequest($uri, 'POST');
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->setBody($body);

        $formEncodedBody = new FormEncodedBody($request);
        $this->assertEquals('one', $formEncodedBody->getBodyParameter('var1'));
    }

    /**
     * @covers Artax\Http\FormEncodedBody::__construct
     * @covers Artax\Http\FormEncodedBody::getBodyParameter
     * @expectedException Spl\DomainException
     */
    public function testGetBodyParameterExceptionOnInvalidParameter() {
        $uri = new Uri('http://localhost');
        $body = 'var1=one&var2=two&var3=Yes%20sir';
        $request = new StdRequest($uri, 'POST');
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->setBody($body);

        $formEncodedBody = new FormEncodedBody($request);
        $formEncodedBody->getBodyParameter('var999999');
    }
}
