<?php

use Artax\Framework\Http\RequestNegotiator,
    Artax\Negotiation\CompositeNegotiator,
    Artax\Negotiation\NegotiatorFactory,
    Artax\Http\StdRequest;

class RequestNegotiatorTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Http\RequestNegotiator::__construct
     */
    public function testConstructorInitializesObject() {
        $request = $this->getMock('Artax\\Http\\Request');
        $composite = new CompositeNegotiator(new NegotiatorFactory);
        $negotiator = new RequestNegotiator($composite);
    }
    
    /**
     * @covers Artax\Framework\Http\RequestNegotiator::negotiate
     * @expectedException LogicException
     */
    public function testNegotiateThrowsExceptionOnMissingContentTypes() {
        $request = $this->getMock('Artax\\Http\\Request');
        $composite = new CompositeNegotiator(new NegotiatorFactory);
        $negotiator = new RequestNegotiator($composite);
        
        $negotiator->setAvailableCharsets(array('utf-8'));
        $negotiator->setAvailableLanguages(array('en'));
        $negotiator->negotiate($request);
    }
    
    /**
     * @covers Artax\Framework\Http\RequestNegotiator::negotiate
     * @expectedException LogicException
     */
    public function testNegotiateThrowsExceptionOnMissingCharsets() {
        $request = $this->getMock('Artax\\Http\\Request');
        $composite = new CompositeNegotiator(new NegotiatorFactory);
        $negotiator = new RequestNegotiator($composite);
        
        $negotiator->setAvailableContentTypes(array('text/hmtl'));
        $negotiator->setAvailableLanguages(array('en'));
        $negotiator->negotiate($request);
    }
    
    /**
     * @covers Artax\Framework\Http\RequestNegotiator::negotiate
     * @expectedException LogicException
     */
    public function testNegotiateThrowsExceptionOnMissingLanguages() {
        $request = $this->getMock('Artax\\Http\\Request');
        $composite = new CompositeNegotiator(new NegotiatorFactory);
        $negotiator = new RequestNegotiator($composite);
        
        $negotiator->setAvailableContentTypes(array('text/hmtl'));
        $negotiator->setAvailableCharsets(array('utf-8'));
        $negotiator->negotiate($request);
    }
    
    /**
     * @covers Artax\Framework\Http\RequestNegotiator::negotiate
     * @covers Artax\Framework\Http\RequestNegotiator::negotiateContentType
     * @covers Artax\Framework\Http\RequestNegotiator::negotiateCharset
     * @covers Artax\Framework\Http\RequestNegotiator::negotiateLanguage
     * @covers Artax\Framework\Http\RequestNegotiator::negotiateEncoding
     * @covers Artax\Framework\Http\RequestNegotiator::setAvailableContentTypes
     * @covers Artax\Framework\Http\RequestNegotiator::setAvailableCharsets
     * @covers Artax\Framework\Http\RequestNegotiator::setAvailableLanguages
     * @covers Artax\Framework\Http\RequestNegotiator::setAvailableEncodings
     */
    public function testNegotiateReturnsAnArrayOfNegotiatedValues() {
        $request = new StdRequest('http://localhost', 'GET');
        $request->setAllHeaders(array(
            'accept' => '*/*',
            'accept-charset' => '*',
            'accept-language' => '*',
            'accept-encoding' => 'gzip, deflate, identity'
        ));
        $composite = new CompositeNegotiator(new NegotiatorFactory);
        $negotiator = new RequestNegotiator($composite);
        
        $negotiator->setAvailableContentTypes(array('text/html', 'application/json'));
        $negotiator->setAvailableCharsets(array('utf-8'));
        $negotiator->setAvailableLanguages(array('en'));
        $negotiator->setAvailableEncodings(array('gzip', 'deflate', 'identity'));
        
        $expected = array(
            'contentType' => 'text/html',
            'charset' => 'utf-8',
            'language' => 'en',
            'encoding' => 'gzip'
        );
        
        $this->assertEquals($expected, $negotiator->negotiate($request));
    }
    
    /**
     * @covers Artax\Framework\Http\RequestNegotiator::negotiateAndApply
     */
    public function testNegotiateAndApplyAssignsResponseHeadersAndReturnsNegotiatedValues() {
        $request = new StdRequest('http://localhost', 'GET');
        $request->setAllHeaders(array(
            'accept' => '*/*',
            'accept-charset' => '*',
            'accept-language' => '*',
            'accept-encoding' => 'gzip, deflate, identity'
        ));
        
        $composite = new CompositeNegotiator(new NegotiatorFactory);
        $negotiator = new RequestNegotiator($composite);
        
        $negotiator->setAvailableContentTypes(array('text/html', 'application/json'));
        $negotiator->setAvailableCharsets(array('utf-8'));
        $negotiator->setAvailableLanguages(array('en'));
        $negotiator->setAvailableEncodings(array('gzip', 'deflate', 'identity'));
        
        $response = new Artax\Http\StdResponse();
        
        $expected = array(
            'contentType' => 'text/html',
            'charset' => 'utf-8',
            'language' => 'en',
            'encoding' => 'gzip'
        );
        
        $this->assertEquals($expected, $negotiator->negotiateAndApply($request, $response));
        
        
        
        $this->assertEquals('text/html; charset=utf-8', $response->getHeader('Content-Type'));
        $this->assertEquals('en', $response->getHeader('Content-Language'));
        $this->assertEquals('gzip', $response->getHeader('Content-Encoding'));
        $this->assertEquals(
            'Accept,Accept-Charset,Accept-Language,Accept-Encoding',
            $response->getHeader('Vary')
        );
    }
}
