<?php

use Artax\Http\Negotiation\Negotiators\ContentTypeNegotiator,
    Artax\Http\Negotiation\Parsers\AcceptParser;

class ContentTypeTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\Negotiation\Negotiators\ContentTypeNegotiator::__construct
     */
    public function testBeginsEmpty() {
        $negotiator = new ContentTypeNegotiator(new AcceptParser);
        $this->assertInstanceOf(
            'Artax\\Http\\Negotiation\\Negotiators\\ContentTypeNegotiator', $negotiator
        );
    }
    
    /**
     * @covers Artax\Http\Negotiation\Negotiators\ContentTypeNegotiator::negotiate
     */
    public function testNegotiateReturnsDefaultContentTypeOnEmptyHeader() {
        $negotiator = new ContentTypeNegotiator(new AcceptParser);
        $this->assertEquals('text/html', $negotiator->negotiate('', array('text/html')));
    }
    
    /**
     * @covers Artax\Http\Negotiation\Negotiators\ContentTypeNegotiator::negotiate
     * @covers Artax\Http\Negotiation\Negotiators\ContentTypeNegotiator::getAcceptablesFromParsedTerms
     * @expectedException Artax\Http\Negotiation\NotAcceptableException
     */
    public function testNegotiateThrowsNotAcceptableOnConflictedAcceptHeader() {
        $negotiator = new ContentTypeNegotiator(new AcceptParser);
        $negotiator->negotiate('text/html;q=0, */*', array('text/html'));
    }
    
    /**
     * @covers Artax\Http\Negotiation\Negotiators\ContentTypeNegotiator::negotiate
     * @covers Artax\Http\Negotiation\Negotiators\ContentTypeNegotiator::getAcceptablesFromParsedTerms
     * @covers Artax\Http\Negotiation\Negotiators\ContentTypeNegotiator::convertStringsToMimeTypes
     * @covers Artax\Http\Negotiation\Negotiators\ContentTypeNegotiator::isRejected
     */
    public function testNegotiateReturnsFirstAcceptableAvailableType() {
        $negotiator = new ContentTypeNegotiator(new AcceptParser);
        $rawHeader = 'text/*;q=0, application/json';
        $available = array('text/html', 'application/json');
        $this->assertEquals('application/json', $negotiator->negotiate($rawHeader, $available));
        
        $rawHeader = 'text/*;q=0.8, application/json;q=0.9';
        $available = array('text/html', 'application/json');
        $this->assertEquals('application/json', $negotiator->negotiate($rawHeader, $available));
        
        $rawHeader = 'text/*;q=0.9, application/json;q=0.7';
        $available = array('text/html', 'application/json');
        $this->assertEquals('text/html', $negotiator->negotiate($rawHeader, $available));
        
        $rawHeader = 'text/*;q=0.9, text/html;q=0, application/json;q=0.7';
        $available = array('text/html', 'application/json', 'text/xhtml');
        $this->assertEquals('text/xhtml', $negotiator->negotiate($rawHeader, $available));
        
        $rawHeader = '*/*, application/json;q=0';
        $available = array('application/json', 'text/html');
        $this->assertEquals('text/html', $negotiator->negotiate($rawHeader, $available));
        
        $rawHeader = '*/*, application/*;q=0';
        $available = array('application/json', 'text/html');
        $this->assertEquals('text/html', $negotiator->negotiate($rawHeader, $available));
    }
    
    /**
     * @covers Artax\Http\Negotiation\Negotiators\ContentTypeNegotiator::negotiate
     * @covers Artax\Http\Negotiation\Negotiators\ContentTypeNegotiator::getAcceptablesFromParsedTerms
     * @expectedException Artax\Http\Negotiation\NotAcceptableException
     */
    public function testNegotiateThrowsExceptionInCrazyWildcardEdgeCase() {
        $negotiator = new ContentTypeNegotiator(new AcceptParser);
        $rawHeader = '*/*, application/*;q=0';
        $available = array('application/json');
        $negotiator->negotiate($rawHeader, $available);
    }
}
