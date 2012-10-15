<?php

use Artax\Negotiation\Parsers\AcceptEncodingParser;

/**
 * @covers Artax\Negotiation\Parsers\AcceptEncodingParser
 */
class AcceptEncodingParserTest extends PHPUnit_Framework_TestCase {
    
    public function testExtendsBaseParser() {
        $parser = new AcceptEncodingParser;
        $this->assertInstanceOf('Artax\\Negotiation\\Parsers\\BaseParser', $parser);
    }
    
    /**
     * @covers Artax\Negotiation\Parsers\AcceptEncodingParser::parse
     * @covers Artax\Negotiation\Parsers\AcceptEncodingParser::sortQualityTie
     * @covers Artax\Negotiation\Parsers\AcceptEncodingParser::getTermsFromRawHeader
     * @covers Artax\Negotiation\Parsers\AcceptEncodingParser::sortByQuality
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
