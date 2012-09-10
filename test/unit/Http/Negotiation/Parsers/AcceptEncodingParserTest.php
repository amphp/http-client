<?php

use Artax\Http\Negotiation\Parsers\AcceptEncodingParser;

/**
 * @covers Artax\Http\Negotiation\Parsers\AcceptEncodingParser
 */
class AcceptEncodingParserTest extends PHPUnit_Framework_TestCase {
    
    public function testExtendsBaseParser() {
        $parser = new AcceptEncodingParser;
        $this->assertInstanceOf('Artax\\Http\\Negotiation\\Parsers\\BaseParser', $parser);
    }
    
    /**
     * @covers Artax\Http\Negotiation\Parsers\AcceptEncodingParser::parse
     * @covers Artax\Http\Negotiation\Parsers\AcceptEncodingParser::sortQualityTie
     * @covers Artax\Http\Negotiation\Parsers\AcceptEncodingParser::getTermsFromRawHeader
     * @covers Artax\Http\Negotiation\Parsers\AcceptEncodingParser::sortByQuality
     */
    public function testParseReturnsEncodingOrderedByQualityAndSpecificity() {
        $parser = new AcceptEncodingParser;
        $rawHeader = 'gzip;q=1.0, bzip, compress, identity; q=0.5, *;q=0';
        $expected = array('gzip', 'bzip', 'compress', 'identity', '*');
        
        $terms = $parser->parse($rawHeader);
        $actual = array_map(function($term) { return (string) $term; }, $terms);
        
        $this->assertSame($expected, $actual);
    }
}
