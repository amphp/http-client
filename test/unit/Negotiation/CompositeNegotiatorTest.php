<?php

use Artax\Negotiation\CompositeNegotiator,
    Artax\Negotiation\NegotiatorFactory;

class CompositeNegotiatorTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Negotiation\CompositeNegotiator::__construct
     * @covers Artax\Negotiation\CompositeNegotiator::negotiateContentType
     */
    public function testNegotiatContentTypeCallsMethodOnFactoryCreatedNegotiator() {
        $negotiator = new CompositeNegotiator(new NegotiatorFactory);
        $this->assertEquals(
            'text/html', $negotiator->negotiateContentType('text/*;q=1', array('text/html'=>1))
        );
    }
    
    /**
     * @covers Artax\Negotiation\CompositeNegotiator::__construct
     * @covers Artax\Negotiation\CompositeNegotiator::negotiateCharset
     */
    public function testNegotiatCharsetCallsMethodOnFactoryCreatedNegotiator() {
        $negotiator = new CompositeNegotiator(new NegotiatorFactory);
        $this->assertEquals(
            'utf-8', $negotiator->negotiateCharset('*', array('utf-8'=>1, 'iso-8859-1'=>1))
        );
    }
    
    /**
     * @covers Artax\Negotiation\CompositeNegotiator::__construct
     * @covers Artax\Negotiation\CompositeNegotiator::negotiateEncoding
     */
    public function testNegotiatEncodingCallsMethodOnFactoryCreatedNegotiator() {
        $negotiator = new CompositeNegotiator(new NegotiatorFactory);
        $this->assertEquals(
            'identity', $negotiator->negotiateEncoding('', array('gzip'=>1, 'deflate'=>1, 'identity'=>1))
        );
    }
    
    /**
     * @covers Artax\Negotiation\CompositeNegotiator::__construct
     * @covers Artax\Negotiation\CompositeNegotiator::negotiateLanguage
     */
    public function testNegotiatLanguageCallsMethodOnFactoryCreatedNegotiator() {
        $negotiator = new CompositeNegotiator(new NegotiatorFactory);
        $this->assertEquals('en-us',
            $negotiator->negotiateLanguage('en-us;q=1, en;q=0.9', array('en'=>1, 'en-us'=>1))
        );
    }
    
}
