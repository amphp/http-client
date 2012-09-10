<?php

use Artax\Http\Negotiation\CompositeNegotiator,
    Artax\Http\Negotiation\NegotiatorFactory;

class CompositeNegotiatorTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\Negotiation\CompositeNegotiator::__construct
     * @covers Artax\Http\Negotiation\CompositeNegotiator::negotiateContentType
     */
    public function testNegotiatContentTypeCallsMethodOnFactoryCreatedNegotiator() {
        $negotiator = new CompositeNegotiator(new NegotiatorFactory);
        $this->assertEquals(
            'text/html', $negotiator->negotiateContentType('text/*;q=1', array('text/html'))
        );
    }
    
    /**
     * @covers Artax\Http\Negotiation\CompositeNegotiator::__construct
     * @covers Artax\Http\Negotiation\CompositeNegotiator::negotiateCharset
     */
    public function testNegotiatCharsetCallsMethodOnFactoryCreatedNegotiator() {
        $negotiator = new CompositeNegotiator(new NegotiatorFactory);
        $this->assertEquals(
            'utf-8', $negotiator->negotiateCharset('*', array('utf-8', 'iso-8859-1'))
        );
    }
    
    /**
     * @covers Artax\Http\Negotiation\CompositeNegotiator::__construct
     * @covers Artax\Http\Negotiation\CompositeNegotiator::negotiateEncoding
     */
    public function testNegotiatEncodingCallsMethodOnFactoryCreatedNegotiator() {
        $negotiator = new CompositeNegotiator(new NegotiatorFactory);
        $this->assertEquals(
            'identity', $negotiator->negotiateEncoding('', array('gzip', 'deflate', 'identity'))
        );
    }
    
    /**
     * @covers Artax\Http\Negotiation\CompositeNegotiator::__construct
     * @covers Artax\Http\Negotiation\CompositeNegotiator::negotiateLanguage
     */
    public function testNegotiatLanguageCallsMethodOnFactoryCreatedNegotiator() {
        $negotiator = new CompositeNegotiator(new NegotiatorFactory);
        $this->assertEquals('en-us',
            $negotiator->negotiateLanguage('en-us;q=1, en;q=0.9', array('en', 'en-us'))
        );
    }
    
}
