<?php

use Artax\Negotiation\EncodingNegotiator;

/**
 * @covers Artax\Negotiation\EncodingNegotiator<extended>
 */
class EncodingNegotiatorTest extends PHPUnit_Framework_TestCase {
    
    public function provideAcceptableEncodings() {
        $return = array();
        
        // 0 ---------------------------------------------------------------------->
        
        $rawHeader = '';
        $availableTypes = array(
            'gzip' => 1,
            'identity' => 0.5
        );
        $expected = 'identity';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 1 ---------------------------------------------------------------------->
        
        $rawHeader = '*';
        $availableTypes = array(
            'gzip' => 0.75,
            'compress' => 0.5
        );
        $expected = 'gzip';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 2 ---------------------------------------------------------------------->
        
        $rawHeader = 'gzip, *;q=0.5';
        $availableTypes = array(
            'gzip' => 1,
            'deflate' => 0.01,
            'identity' => 0.9
        );
        $expected = 'gzip';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 3 ---------------------------------------------------------------------->
        
        $rawHeader = 'identity, gzip;q=0';
        $availableTypes = array(
            'gzip' => 0.9,
            'identity' => 0.8
        );
        $expected = 'identity';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 4 ---------------------------------------------------------------------->
        
        $rawHeader = '*;q=0.1, gzip;q=0';
        $availableTypes = array(
            'gzip' => 1,
            'identity' => 0.01
        );
        $expected = 'identity';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // x ---------------------------------------------------------------------->
        
        return $return;
    }
    
    /**
     * @dataProvider provideAcceptableEncodings
     */
    public function testNegotiate($rawHeader, $availableTypes, $expected) {
        $negotiator = new EncodingNegotiator();
        $this->assertEquals($expected, $negotiator->negotiate($rawHeader, $availableTypes));
    }
    
    public function provideNotAcceptableEncodings() {
        $return = array();
        
        // 0 ---------------------------------------------------------------------->
        
        $rawHeader = '*, identity;q=0';
        $availableTypes = array(
            'identity' => 1
        );
        $return[] = array($rawHeader, $availableTypes);
        
        // x ---------------------------------------------------------------------->
        
        return $return;
    }
    
    /**
     * @dataProvider provideNotAcceptableEncodings
     * @expectedException Artax\Negotiation\NotAcceptableException
     */
    public function testNegotiateThrowsExceptionIfNotAcceptable($rawHeader, $availableTypes) {
        $negotiator = new EncodingNegotiator();
        $negotiator->negotiate($rawHeader, $availableTypes);
    }
    
    public function provideInvalidAvailableTypes() {
        $return = array();
        
        $return[] = array(array(
            'gzip' => 1,
            'identity' => 0.9,
            '*' => 0.5 // <-- wildcards not allowed in available types list
        ));
        $return[] = array(array(
            'gzip' => 1.01, // <-- must be 0 < $x < 1
            'deflate' => 0.5,
            'identity' => 0.4
        ));
        $return[] = array(array(
            'identity' => 0, // <-- must be 0 < $x < 1
            'gzip' => 0.5
        ));
        $return[] = array(array(
            'gzip' => 'test', // <-- must be numeric
            'identity' => 0.5
        ));
        
        return $return;
    }
    
    /**
     * @dataProvider provideInvalidAvailableTypes
     * @expectedException Spl\ValueException
     */
    public function testNegotiateThrowsExceptionOnInvalidAvailableTypes($availableTypes) {
        $n = new EncodingNegotiator();
        $n->negotiate('gzip, identity', $availableTypes);
    }
}
