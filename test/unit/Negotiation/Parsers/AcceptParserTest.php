<?php

use Artax\Negotiation\Parsers\AcceptParser;

/**
 * @covers Artax\Negotiation\Parsers\AcceptParser<extended>
 */
class AcceptParserTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Negotiation\Parsers\AcceptParser::parse
     * @covers Artax\Negotiation\Parsers\AcceptParser::getTermsFromRawHeader
     * @covers Artax\Negotiation\Parsers\AcceptParser::sortByQuality
     * @covers Artax\Negotiation\Parsers\AcceptParser::sortQualityTie
     */
    public function testParseReturnsContentTypeTermsOrderedByQualityAndSpecificity() {
        $parser = new AcceptParser;
        
        $rawHeader = 'text/*, text/html, text/html;level=1, */*';
        $terms = $parser->parse($rawHeader);
        $this->assertInstanceOf('Artax\\Negotiation\\MediaRange', $terms[0]->getType());
        
        $expected = array('text/html', 'text/html', 'text/*', '*/*');
        $actual = array(
            (string) $terms[0],
            (string) $terms[1],
            (string) $terms[2],
            (string) $terms[3]
        );
        
        $this->assertEquals($expected, $actual);
        $this->assertEquals(1, $terms[0]->getQuality());
        $this->assertEquals(true, $terms[0]->hasExplicitQuality());
        
        
        $rawHeader = '*/*, text/*, application/*, application/json, text/html;level=1';
        $terms = $parser->parse($rawHeader);
        $expected = array('text/html', 'application/json', 'text/*', 'application/*', '*/*');
        $actual = array(
            (string) $terms[0],
            (string) $terms[1],
            (string) $terms[2],
            (string) $terms[3],
            (string) $terms[4]
        );
        $this->assertEquals($expected, $actual);
        
        
        $rawHeader = '*/*;q=0, discarded, text/xhtml;q=0, application/json;level=0.9, text/html';
        $terms = $parser->parse($rawHeader);
        $expected = array('text/html', 'application/json', 'text/xhtml');
        $actual = array(
            (string) $terms[0],
            (string) $terms[1],
            (string) $terms[2]
        );
        $this->assertEquals($expected, $actual);
        $this->assertEquals(0, $terms[2]->getQuality());
    }
}
