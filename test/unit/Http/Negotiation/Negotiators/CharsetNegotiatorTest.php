<?php

use Artax\Http\Negotiation\Negotiators\CharsetNegotiator,
    Artax\Http\Negotiation\Parsers\AcceptCharsetParser;

class CharsetNegotiatorTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\Negotiation\Negotiators\CharsetNegotiator::__construct
     */
    public function testBeginsEmpty() {
        $negotiator = new CharsetNegotiator(new AcceptCharsetParser);
        $this->assertInstanceOf(
            'Artax\\Http\\Negotiation\\Negotiators\\CharsetNegotiator', $negotiator
        );
    }
    
    /**
     * @covers Artax\Http\Negotiation\Negotiators\CharsetNegotiator::negotiate
     */
    public function testNegotiateCharsetReturnsDefaultCharsetOnEmptyHeader() {
        $negotiator = new CharsetNegotiator(new AcceptCharsetParser);
        $this->assertEquals('iso-8859-5', $negotiator->negotiate('', array('iso-8859-5')));
    }
    
    /**
     * @covers Artax\Http\Negotiation\Negotiators\CharsetNegotiator::negotiate
     */
    public function testNegotiateCharsetReturnsFirstAcceptableAvailableType() {
        $negotiator = new CharsetNegotiator(new AcceptCharsetParser);
        
        $rawHeader = '*';
        $availableTypes = array('iso-8859-5', 'unicode-1-1');
        $this->assertEquals('iso-8859-5', $negotiator->negotiate($rawHeader, $availableTypes));
        
        $rawHeader = 'utf-8, *;q=0.5';
        $availableTypes = array('iso-8859-5', 'unicode-1-1', 'utf-8');
        $this->assertEquals('utf-8', $negotiator->negotiate($rawHeader, $availableTypes));
        
        $rawHeader = 'utf-8, iso-8859-5;q=0';
        $availableTypes = array('iso-8859-5', 'utf-8', 'unicode-1-1');
        $this->assertEquals('utf-8', $negotiator->negotiate($rawHeader, $availableTypes));
        
        $rawHeader = 'utf-8;q=0, *';
        $availableTypes = array('utf-8', 'unicode-1-1');
        $this->assertEquals('unicode-1-1', $negotiator->negotiate($rawHeader, $availableTypes));
        
        $rawHeader = 'utf-8;q=0.8, *;q=0.9';
        $availableTypes = array('utf-8', 'unicode-1-1');
        $this->assertEquals('utf-8', $negotiator->negotiate($rawHeader, $availableTypes));
    }
    
    /**
     * @covers Artax\Http\Negotiation\Negotiators\CharsetNegotiator::negotiate
     * @expectedException Artax\Http\Negotiation\NotAcceptableException
     */
    public function testNegotiateCharsetThrowsExceptionIfNoAcceptableCharsetsAvailabe() {
        $negotiator = new CharsetNegotiator(new AcceptCharsetParser);
        
        $rawHeader = 'iso-8859-5;level=1, *;q=0';
        $availableTypes = array('utf-8');
        $negotiator->negotiate($rawHeader, $availableTypes);
    }
    
    /**
     * @covers Artax\Http\Negotiation\Negotiators\CharsetNegotiator::negotiate
     * @expectedException Artax\Http\Negotiation\NotAcceptableException
     */
    public function testNegotiateCharsetThrowsAnotherExceptionOnNoAcceptableCharsetAvailability() {
        $negotiator = new CharsetNegotiator(new AcceptCharsetParser);
        
        $rawHeader = 'iso-8859-5;level=1';
        $availableTypes = array('utf-8');
        $negotiator->negotiate($rawHeader, $availableTypes);
    }
    
    /**
     * @covers Artax\Http\Negotiation\Negotiators\CharsetNegotiator::negotiate
     * @expectedException Artax\Http\Negotiation\NotAcceptableException
     */
    public function testNegotiateCharsetThrowsYetAnotherExceptionOnNoAcceptableCharsetAvailability() {
        $negotiator = new CharsetNegotiator(new AcceptCharsetParser);
        
        $rawHeader = '*, utf-8;q=0';
        $availableTypes = array('utf-8');
        $negotiator->negotiate($rawHeader, $availableTypes);
    }
}
