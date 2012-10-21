<?php

use Artax\Negotiation\LanguageNegotiator;

/**
 * @covers Artax\Negotiation\LanguageNegotiator
 * @covers Artax\Negotiation\Terms\LanguageTerm
 * @covers Artax\Negotiation\Terms\Term
 */
class LanguageNegotiatorTest extends PHPUnit_Framework_TestCase {
    
    public function provideAcceptableLanguages() {
        $return = array();
        
        // 0 ---------------------------------------------------------------------->
        
        $rawHeader = '';
        $availableTypes = array(
            'en-us' => 1
        );
        $expected = 'en-us';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 1 ---------------------------------------------------------------------->
        
        $rawHeader = '*';
        $availableTypes = array(
            'en-us' => 1,
            'da' => 0.9
        );
        $expected = 'en-us';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 2 ---------------------------------------------------------------------->
        
        $rawHeader = 'en-us, en;q=0.9, *;q=0.5';
        $availableTypes = array(
            'en-gb' => 1, 
            'en-us' => 0.5, 
            'da' => 0.1
        );
        $expected = 'en-gb';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 3 ---------------------------------------------------------------------->
        
        $rawHeader = 'en, en-us;q=0';
        $availableTypes = array(
            'en-us' => 1,
            'en-gb' => 0.25
        );
        $expected = 'en-gb';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 4 ---------------------------------------------------------------------->
        
        $rawHeader = 'en-us;q=0, *';
        $availableTypes = array(
            'en-us' => 1,
            'en-gb' => 0.3
        );
        $expected = 'en-gb';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // 5 ---------------------------------------------------------------------->
        
        $rawHeader = 'en, en-us, en-gb';
        $availableTypes = array(
            'en-us' => 1,
            'en-gb' => 1
        );
        $expected = 'en-us';
        $return[] = array($rawHeader, $availableTypes, $expected);
        
        // x ---------------------------------------------------------------------->
        
        return $return;
    }
    
    /**
     * @dataProvider provideAcceptableLanguages
     */
    public function testNegotiate($rawHeader, $availableTypes, $expected) {
        $negotiator = new LanguageNegotiator();
        $actual = $negotiator->negotiate($rawHeader, $availableTypes);
        $this->assertEquals($expected, $actual);
    }
    
    public function provideNotAcceptableLanguages() {
        $return = array();
        
        // 0 ---------------------------------------------------------------------->
        
        $rawHeader = 'en;q=0, *';
        $availableTypes = array(
            'en-us' => 1
        );
        $return[] = array($rawHeader, $availableTypes);
        
        // x ---------------------------------------------------------------------->
        
        return $return;
    }
    
    /**
     * @dataProvider provideNotAcceptableLanguages
     * @expectedException Artax\Negotiation\NotAcceptableException
     */
    public function testNegotiateThrowsExceptionIfNotAcceptable($rawHeader, $availableTypes) {
        $negotiator = new LanguageNegotiator();
        $negotiator->negotiate($rawHeader, $availableTypes);
    }
}
