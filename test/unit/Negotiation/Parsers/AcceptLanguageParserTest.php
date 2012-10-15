<?php

use Artax\Negotiation\Parsers\AcceptLanguageParser;

/**
 * @covers Artax\Negotiation\Parsers\AcceptLanguageParser<extended>
 */
class AcceptLanguageTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Negotiation\Parsers\AcceptLanguageParser::parse
     * @covers Artax\Negotiation\Parsers\AcceptLanguageParser::sortQualityTie
     * @covers Artax\Negotiation\Parsers\AcceptLanguageParser::getTermsFromRawHeader
     * @covers Artax\Negotiation\Parsers\AcceptLanguageParser::sortByQuality
     */
    public function testParseReturnsLanguageOrderedByQualityAndSpecificity() {
        $parser = new AcceptLanguageParser;
        
        $rawHeader = 'da, en-gb;q=0.8, en;q=0.7';
        $terms = $parser->parse($rawHeader);
        $actual = array_map(function($term) { return (string) $term; }, $terms);
        $expected = array('da', 'en-gb', 'en');
        
        $this->assertSame($expected, $actual);
    }
}
