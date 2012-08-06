<?php

use Artax\Framework\Http\RequestNegotiator,
    Artax\Negotiation\CompositeNegotiator,
    Artax\Negotiation\NegotiatorFactory;

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
        $request = new RequestNegotiatorMockRequest;
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
        $request = new RequestNegotiatorMockRequest;
        $composite = new CompositeNegotiator(new NegotiatorFactory);
        $negotiator = new RequestNegotiator($composite);
        
        $negotiator->setAvailableContentTypes(array('text/html', 'application/json'));
        $negotiator->setAvailableCharsets(array('utf-8'));
        $negotiator->setAvailableLanguages(array('en'));
        $negotiator->setAvailableEncodings(array('gzip', 'deflate', 'identity'));
        
        $response = new Artax\Http\MutableStdResponse();
        
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


class RequestNegotiatorMockRequest implements Artax\Http\Request {
    
    function getHeader($header) {
        $header = strtolower($header);
        switch ($header) {
            case 'accept': return '*/*';
            case 'accept-charset': return '*';
            case 'accept-language': return '*';
            case 'accept-encoding': return 'gzip, deflate, identity';
            default: return '';
        }
    }
    
    function hasHeader($header) { 
        return true;
    }
    
    function getUri() { return null; }
    function getRawUri() { return null; }
    function getAuthority() { return null; }
    function getRawAuthority() { return null; }
    function getUserInfo() { return null; }
    function getRawUserInfo() { return null; }
    function __toString() { return null; }
    
    function getMethod() { return null; }
    function getScheme() { return null; }
    function getHost() { return null; }
    function getPort() { return null; }
    function getFragment() { return null; }
    function getQuery() { return null; }
    function getPath() { return null; }
    function getHttpVersion() { return null; }
    function getAllHeaders() { return null; }
    function getBody() { return null; }
    function getBodyStream() { return null; }
    function getQueryParameter($parameter) { return null; }
    function getAllQueryParameters() { return null; }
    function hasQueryParameter($parameter) { return null; }
    function getBodyParameter($parameter) { return null; }
    function getAllBodyParameters() { return null; }
    function hasBodyParameter($parameter) { return null; }
}
