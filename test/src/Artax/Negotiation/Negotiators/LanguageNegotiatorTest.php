<?php

use Artax\Negotiation\Negotiators\LanguageNegotiator,
    Artax\Negotiation\Parsers\AcceptLanguageParser;

class LanguageTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Negotiation\Negotiators\LanguageNegotiator::__construct
     */
    public function testBeginsEmpty() {
        $negotiator = new LanguageNegotiator(new AcceptLanguageParser);
        $this->assertInstanceOf(
            'Artax\\Negotiation\\Negotiators\\LanguageNegotiator', $negotiator
        );
    }
    
    /**
     * @covers Artax\Negotiation\Negotiators\LanguageNegotiator::negotiate
     */
    public function testNegotiateReturnsDefaultOnEmptyHeader() {
        $negotiator = new LanguageNegotiator(new AcceptLanguageParser);
        $this->assertEquals('en-us', $negotiator->negotiate('', array('en-us')));
    }
    
    /**
     * @covers Artax\Negotiation\Negotiators\LanguageNegotiator::negotiate
     * @covers Artax\Negotiation\Negotiators\LanguageNegotiator::buildAvailableRanges
     * @covers Artax\Negotiation\Negotiators\LanguageNegotiator::selectWildcardLang
     */
    public function testNegotiateReturnsFirstAcceptableAvailableType() {
        $negotiator = new LanguageNegotiator(new AcceptLanguageParser);
        $rawHeader = '*';
        $available = array('en-us', 'da');
        $this->assertEquals('en-us', $negotiator->negotiate($rawHeader, $available));
        
        $rawHeader = 'en-us, en;q=0.9, *;q=0.5';
        $available = array('en-gb', 'en-us', 'da');
        $this->assertEquals('en-us', $negotiator->negotiate($rawHeader, $available));
        
        $rawHeader = 'en, en-us;q=0';
        $available = array('en-us', 'en-gb');
        $this->assertEquals('en-gb', $negotiator->negotiate($rawHeader, $available));
        
        $rawHeader = 'en-us;q=0, *';
        $available = array('en-us', 'en-gb');
        $this->assertEquals('en-gb', $negotiator->negotiate($rawHeader, $available));
    }
    
    /**
     * @covers Artax\Negotiation\Negotiators\LanguageNegotiator::negotiate
     * @covers Artax\Negotiation\Negotiators\LanguageNegotiator::buildAvailableRanges
     * @covers Artax\Negotiation\Negotiators\LanguageNegotiator::selectWildcardLang
     * @expectedException Artax\Negotiation\NotAcceptableException
     */
    public function testNegotiateThrowsExceptionOnCrazyWildcardEdgeCase() {
        $negotiator = new LanguageNegotiator(new AcceptLanguageParser);
        $rawHeader = 'en;q=0, *';
        $available = array('en-us');
        $negotiator->negotiate($rawHeader, $available);
    }
}
