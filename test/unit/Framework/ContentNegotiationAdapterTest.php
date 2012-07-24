<?php

use Artax\Framework\ContentNegotiationAdapter,
    Artax\Negotiation\CompositeNegotiator,
    Artax\Negotiation\NegotiatorFactory;

class ContentNegotiationAdapterTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\ContentNegotiationAdapter::__construct
     */
    public function testConstructorInitializesObject() {
        $request = $this->getMock('Artax\\Http\\Request');
        $composite = new CompositeNegotiator(new NegotiatorFactory);
        $negotiator = new ContentNegotiationAdapter($composite);
    }
    
    /**
     * @covers Artax\Framework\ContentNegotiationAdapter::negotiate
     * @expectedException LogicException
     */
    public function testNegotiateThrowsExceptionOnMissingContentTypes() {
        $request = $this->getMock('Artax\\Http\\Request');
        $composite = new CompositeNegotiator(new NegotiatorFactory);
        $negotiator = new ContentNegotiationAdapter($composite);
        
        $negotiator->setAvailableCharsets(array('utf-8'));
        $negotiator->setAvailableLanguages(array('en'));
        $negotiator->negotiate($request);
    }
    
    /**
     * @covers Artax\Framework\ContentNegotiationAdapter::negotiate
     * @expectedException LogicException
     */
    public function testNegotiateThrowsExceptionOnMissingCharsets() {
        $request = $this->getMock('Artax\\Http\\Request');
        $composite = new CompositeNegotiator(new NegotiatorFactory);
        $negotiator = new ContentNegotiationAdapter($composite);
        
        $negotiator->setAvailableContentTypes(array('text/hmtl'));
        $negotiator->setAvailableLanguages(array('en'));
        $negotiator->negotiate($request);
    }
    
    /**
     * @covers Artax\Framework\ContentNegotiationAdapter::negotiate
     * @expectedException LogicException
     */
    public function testNegotiateThrowsExceptionOnMissingLanguages() {
        $request = $this->getMock('Artax\\Http\\Request');
        $composite = new CompositeNegotiator(new NegotiatorFactory);
        $negotiator = new ContentNegotiationAdapter($composite);
        
        $negotiator->setAvailableContentTypes(array('text/hmtl'));
        $negotiator->setAvailableCharsets(array('utf-8'));
        $negotiator->negotiate($request);
    }
    
    /**
     * @covers Artax\Framework\ContentNegotiationAdapter::negotiate
     * @covers Artax\Framework\ContentNegotiationAdapter::negotiateContentType
     * @covers Artax\Framework\ContentNegotiationAdapter::negotiateCharset
     * @covers Artax\Framework\ContentNegotiationAdapter::negotiateLanguage
     * @covers Artax\Framework\ContentNegotiationAdapter::negotiateEncoding
     * @covers Artax\Framework\ContentNegotiationAdapter::setAvailableContentTypes
     * @covers Artax\Framework\ContentNegotiationAdapter::setAvailableCharsets
     * @covers Artax\Framework\ContentNegotiationAdapter::setAvailableLanguages
     * @covers Artax\Framework\ContentNegotiationAdapter::setAvailableEncodings
     */
    public function testNegotiateReturnsAnArrayOfNegotiatedValues() {
        $request = new ContentNegotiationAdapterMockRequest;
        $composite = new CompositeNegotiator(new NegotiatorFactory);
        $negotiator = new ContentNegotiationAdapter($composite);
        
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
     * @covers Artax\Framework\ContentNegotiationAdapter::negotiateAndApply
     */
    public function testNegotiateAndApplyAssignsResponseHeadersAndReturnsNegotiatedValues() {
        $request = new ContentNegotiationAdapterMockRequest;
        $composite = new CompositeNegotiator(new NegotiatorFactory);
        $negotiator = new ContentNegotiationAdapter($composite);
        
        $negotiator->setAvailableContentTypes(array('text/html', 'application/json'));
        $negotiator->setAvailableCharsets(array('utf-8'));
        $negotiator->setAvailableLanguages(array('en'));
        $negotiator->setAvailableEncodings(array('gzip', 'deflate', 'identity'));
        
        $injector = new Artax\Injection\Provider(new Artax\Injection\ReflectionPool);
        $mediator = new Artax\Events\Notifier($injector);
        $response = new Artax\Http\StdResponse($mediator);
        
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


class ContentNegotiationAdapterMockRequest implements Artax\Http\Request {
    
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
    
    function getMethod() { return null; }
    function getUri() { return null; }
    function getScheme() { return null; }
    function getHost() { return null; }
    function getPort() { return null; }
    function getFragment() { return null; }
    function getQuery() { return null; }
    function getPath() { return null; }
    function getHttpVersion() { return null; }
    function getAllHeaders() { return null; }
    function getBody() { return null; }
    function getQueryParameter($parameter) { return null; }
    function getAllQueryParameters() { return null; }
    function hasQueryParameter($parameter) { return null; }
    function getBodyParameter($parameter) { return null; }
    function getAllBodyParameters() { return null; }
    function hasBodyParameter($parameter) { return null; }
}
