<?php

use Artax\Negotiation\Terms\ContentTypeTerm;

/**
 * @covers Artax\Negotiation\Terms\ContentTypeTerm<extended>
 * @covers Artax\Negotiation\Terms\MultipartTerm
 */
class ContentTypeTermTest extends PHPUnit_Framework_TestCase {
    
    public function provideRangeMatches() {
        $return = array();
        
        $type = 'text/html';
        $match = 'text/html';
        $return[] = array($type, $match);
        
        $type = 'text/*';
        $match = 'text/html';
        $return[] = array($type, $match);
        
        $type = '*/*';
        $match = 'text/html';
        $return[] = array($type, $match);
        
        return $return;
    }
    
    /**
     * @dataProvider provideRangeMatches
     */
    public function testRangeMatches($type, $match) {
        $term = new ContentTypeTerm(0, $type, 1, true);
        $this->assertTrue($term->rangeMatches($match));
    }
    
}