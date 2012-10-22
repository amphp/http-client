<?php

use Artax\Negotiation\ContentTypeNegotiator;

class ContentTypeNegotiatorTest extends PHPUnit_Framework_TestCase {
    
    public function provideNegotiationExpectations() {
        $return = array();
        
        // 0 ---------------------------------------------------------------------->
        
        $rawHeader = 'text/*;q=0, application/json';
        $availableTypes = array(
            'text/html' => 1,
            'application/json' => 0.1
        );
        $expected = 'application/json';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 1 ---------------------------------------------------------------------->
        
        $rawHeader = 'text/*;level=0.8, application/json;level=0.9';
        $availableTypes = array(
            'text/html' => 1,
            'application/json' => 1
        );
        $expected = 'application/json';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 2 ---------------------------------------------------------------------->
        
        $rawHeader = 'text/*;q=0.9, application/json;q=0.7';
        $availableTypes = array(
            'text/html' => 1,
            'application/json' => 1
        );
        $expected = 'text/html';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 3 ---------------------------------------------------------------------->
        
        /**
         * When there's a conflict between two terms, we NEVER return an explicitly
         * rejected term. In this case that means that even though `text/*` is acceptable
         * at a quality value of `q=0.9`, we won't return `text/html` because it is
         * explicitly rejected by `text/html;q=0`
         */
        $rawHeader = 'text/*;q=0.9, text/html;q=0, application/json;q=0.7';
        $availableTypes = array(
            'text/html' => 1,
            'application/json' => 0.2,
            'text/xhtml' => 0.2
        );
        $expected = 'text/xhtml';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 4 ---------------------------------------------------------------------->
        
        $rawHeader = '*/*, application/json;q=0';
        $availableTypes = array(
            'application/json' => 1,
            'text/html' => 0.1
        );
        $expected = 'text/html';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 5 ---------------------------------------------------------------------->
        
        /**
         * All other factors being equal, the most specific term declaration should win.
         * In this case that means we should return `application/json` because it has
         * an explicit `q=1` while the wildcard declartion has an implicit value of 1.
         */
        $rawHeader = '*/*, application/json;q=1';
        $availableTypes = array(
            'application/json' => 0.5,
            'text/html' => 0.5
        );
        $expected = 'application/json';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 6 ---------------------------------------------------------------------->
        
        /**
         * If no raw header is specified, return the most preferable available type
         */
        $rawHeader = '';
        $availableTypes = array(
            'txt/xml' => 0.1,
            'application/json' => 1,
            'text/html' => 0.5
        );
        $expected = 'application/json';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 7 ---------------------------------------------------------------------->
        
        /**
         * The negotiator should ignore the specification of invalid media ranges in the
         * raw HTTP Accept header
         */
        $rawHeader = '*/html;q=1, invalid2;q0.9, text/*;q=0.5, */*;q=0.1';
        $availableTypes = array(
            'txt/xml' => 0.1,
            'application/json' => 1,
            'text/html' => 0.5
        );
        $expected = 'application/json';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 8 ---------------------------------------------------------------------->
        
        $rawHeader = '*/*';
        $availableTypes = array(
            'txt/xml' => 0.1,
            'text/html' => 1
        );
        $expected = 'text/html';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // x ---------------------------------------------------------------------->
        
        return $return;
    }
    
    /**
     * @dataProvider provideNegotiationExpectations
     */
    public function testContentTypeNegotiation($rawHeader, $availableTypes, $expected) {
        $n = new ContentTypeNegotiator();
        $this->assertEquals(
            $expected,
            $n->negotiate($rawHeader, $availableTypes)
        );
    }
    
    public function provideNotAcceptableExpectations() {
        $return = array();
        
        // 0 ---------------------------------------------------------------------->
        
        $rawHeader = '*/*, application/*;q=0';
        $availableTypes = array(
            'application/json' => 1
        );
        $return[] = array($rawHeader, $availableTypes);
        
        // x ---------------------------------------------------------------------->
        
        return $return;
    }
    
    /**
     * @dataProvider provideNotAcceptableExpectations
     * @expectedException Artax\Negotiation\NotAcceptableException
     */
    public function testNotAcceptableCases($rawHeader, $availableTypes) {
        $n = new ContentTypeNegotiator();
        $n->negotiate($rawHeader, $availableTypes);
    }
    
    public function provideInvalidAvailableTypes() {
        $return = array();
        
        $return[] = array(array(
            'text/html' => 1,
            'text/*' => 0.5 // <-- wildcards not allowed in available types list
        ));
        $return[] = array(array(
            'text/html' => 1.01, // <-- must be 0 < $x < 1
            'text/xml' => 0.5
        ));
        $return[] = array(array(
            'text/html' => 0, // <-- must be 0 < $x < 1
            'text/xml' => 0.5
        ));
        $return[] = array(array(
            'text/html' => 'test', // <-- must be numeric
            'text/xml' => 0.5
        ));
        
        return $return;
    }
    
    /**
     * @dataProvider provideInvalidAvailableTypes
     * @expectedException Spl\ValueException
     */
    public function testNegotiateThrowsExceptionOnInvalidAvailableTypes($availableTypes) {
        $n = new ContentTypeNegotiator();
        $n->negotiate('', $availableTypes);
    }
}
