<?php

use Artax\Negotiation\Negotiators\EncodingNegotiator,
    Artax\Negotiation\Parsers\AcceptEncodingParser;

class EncodingTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Negotiation\Negotiators\EncodingNegotiator::__construct
     */
    public function testBeginsEmpty() {
        $negotiator = new EncodingNegotiator(new AcceptEncodingParser);
        $this->assertInstanceOf(
            'Artax\\Negotiation\\Negotiators\\EncodingNegotiator', $negotiator
        );
    }
    
    /**
     * @covers Artax\Negotiation\Negotiators\EncodingNegotiator::negotiate
     */
    public function testNegotiateEncodingReturnsDefaultOnEmptyHeader() {
        $negotiator = new EncodingNegotiator(new AcceptEncodingParser);
        $this->assertEquals('identity', $negotiator->negotiate('', array('gzip', 'identity')));
    }
    
    /**
     * @covers Artax\Negotiation\Negotiators\EncodingNegotiator::negotiate
     * @expectedException Artax\Negotiation\NotAcceptableException
     */
    public function testNegotiateEncodingThrowsExceptionIfNoAcceptedEncodingsAvailable() {
        $negotiator = new EncodingNegotiator(new AcceptEncodingParser);
        $negotiator->negotiate('*, identity;q=0', array('identity'));
    }
    
    /**
     * @covers Artax\Negotiation\Negotiators\EncodingNegotiator::negotiate
     */
    public function testNegotiateEncodingReturnsFirstAcceptableAvailableType() {
        $negotiator = new EncodingNegotiator(new AcceptEncodingParser);
        
        $rawHeader = '*';
        $availableTypes = array('gzip', 'compress');
        $this->assertEquals('gzip', $negotiator->negotiate($rawHeader, $availableTypes));
        
        $rawHeader = 'gzip, *;q=0.5';
        $availableTypes = array('gzip', 'compress', 'identity');
        $this->assertEquals('gzip', $negotiator->negotiate($rawHeader, $availableTypes));
        
        $rawHeader = 'identity, gzip;q=0';
        $availableTypes = array('gzip', 'identity');
        $this->assertEquals('identity', $negotiator->negotiate($rawHeader, $availableTypes));
        
        $rawHeader = '*;q=0.1, gzip;q=0';
        $availableTypes = array('gzip', 'identity');
        $this->assertEquals('identity', $negotiator->negotiate($rawHeader, $availableTypes));
    }
}
