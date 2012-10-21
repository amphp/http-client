<?php

use Artax\Negotiation\CharsetNegotiator;

class CharsetNegotiatorTest extends PHPUnit_Framework_TestCase {
    
    public function provideAcceptableCharsets() {
        $return = array();
        
        // 0 ---------------------------------------------------------------------->
        
        $rawHeader = '';
        $availableTypes = array(
            'iso-8859-5' => 1
        );
        $expected = 'iso-8859-5';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 1 ---------------------------------------------------------------------->
        
        $rawHeader = '*';
        $availableTypes = array(
            'iso-8859-5' => 1,
            'unicode-1-1' => 0.9
        );
        $expected = 'iso-8859-5';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 2 ---------------------------------------------------------------------->
        
        $rawHeader = 'utf-8, *;q=0.5';
        $availableTypes = array(
            'iso-8859-5' => 1,
            'unicode-1-1' => 1,
            'utf-8' => 1
        );
        $expected = 'utf-8';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 3 ---------------------------------------------------------------------->
        
        $rawHeader = 'utf-8, iso-8859-5;q=0';
        $availableTypes = array(
            'iso-8859-5' => 1,
            'utf-8' => 1,
            'unicode-1-1' => 1
        );
        $expected = 'utf-8';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 4 ---------------------------------------------------------------------->
        
        $rawHeader = 'utf-8;level=0, *';
        $availableTypes = array(
            'utf-8' => 1,
            'unicode-1-1' => 1
        );
        $expected = 'unicode-1-1';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 5 ---------------------------------------------------------------------->
        
        $rawHeader = 'utf-8;q=0.8, *;q=0.9';
        $availableTypes = array(
            'utf-8' => 1,
            'unicode-1-1' => 1
        );
        $expected = 'unicode-1-1';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // x ---------------------------------------------------------------------->
        
        return $return;
    }
    
    /**
     * @dataProvider provideAcceptableCharsets
     */
    public function testNegotiate($rawHeader, $availableTypes, $expected) {
        $negotiator = new CharsetNegotiator();
        $this->assertEquals($expected, $negotiator->negotiate($rawHeader, $availableTypes));
    }
    
    public function provideNotAcceptableCharsets() {
        $return = array();
        
        // 0 ---------------------------------------------------------------------->
        
        $rawHeader = 'iso-8859-5;level=1, *;q=0';
        $availableTypes = array(
            'utf-8' => 1
        );
        $return[] = array($rawHeader, $availableTypes);
        
        // 1 ---------------------------------------------------------------------->
        
        $rawHeader = 'iso-8859-5;level=1';
        $availableTypes = array(
            'utf-8' => 1
        );
        $return[] = array($rawHeader, $availableTypes);
        
        // 2 ---------------------------------------------------------------------->
        
        $rawHeader = '*, utf-8;q=0';
        $availableTypes = array(
            'utf-8' => 1
        );
        $return[] = array($rawHeader, $availableTypes);
        
        // x ---------------------------------------------------------------------->
        
        return $return;
    }
    
    /**
     * @dataProvider provideNotAcceptableCharsets
     * @expectedException Artax\Negotiation\NotAcceptableException
     */
    public function testNegotiationThrowsExceptionIfNotAcceptable($rawHeader, $availableTypes) {
        $negotiator = new CharsetNegotiator();
        $negotiator->negotiate($rawHeader, $availableTypes);
    }
}
