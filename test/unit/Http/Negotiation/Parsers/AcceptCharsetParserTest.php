<?php

use Artax\Http\Negotiation\Parsers\AcceptCharsetParser;

class AcceptCharsetTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\Negotiation\Parsers\AcceptCharsetParser::getTermsFromRawHeader
     * @covers Artax\Http\Negotiation\Parsers\AcceptCharsetParser::sortByQuality
     * @covers Artax\Http\Negotiation\Parsers\AcceptCharsetParser::sortQualityTie
     * @covers Artax\Http\Negotiation\Parsers\AcceptCharsetParser::parse
     * @covers Artax\Http\Negotiation\Parsers\AcceptCharsetParser::coalesceWildcardAndIso88591
     */
    public function testParseReturnsCharsetsOrderedByQualityAndSpecificity() {
        $parser = new AcceptCharsetParser;
        
        $rawHeader = 'iso-8859-5, unicode-1-1;q=0.8, iso-8859-5, *;q=0.5';
        $terms = $parser->parse($rawHeader);
        $expected = array('iso-8859-5', 'iso-8859-5', 'unicode-1-1', '*');
        $actual = array_map(function($term) { return (string) $term; }, $terms);
        $this->assertSame($expected, $actual);
        
        $rawHeader = 'iso-8859-1, *;q=0.5';
        $terms = $parser->parse($rawHeader);
        $expected = array('iso-8859-1', '*');
        $actual = array_map(function($term) { return (string) $term; }, $terms);
        $this->assertSame($expected, $actual);
        
        
        $rawHeader = 'utf-8';
        $terms = $parser->parse($rawHeader);
        $expected = array('utf-8', 'iso-8859-1');
        $actual = array_map(function($term) { return (string) $term; }, $terms);
        $this->assertSame($expected, $actual);
    }
}
