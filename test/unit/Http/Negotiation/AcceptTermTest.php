<?php

use Artax\Http\Negotiation\AcceptTerm;

class AcceptTermTest extends PHPUnit_Framework_TestCase {
    
    public function providesValidTermConstructorArgs() {
        return array(
            array(0, '*/*', 1.0, true),
            array(1, 'text/html', 1, false),
            array(1, 'application/json', 1, false),
            array(1, 'iso-8859-1', 0.7, true),
            array(1, '*', 0, true),
        );
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Http\Negotiation\AcceptTerm::__construct
     */
    public function testConstructorInitializesProperties($pos, $type, $quality, $explicit) {
        $term = new AcceptTerm($pos, $type, $quality, $explicit);
        $this->assertInstanceOf('Artax\\Http\\Negotiation\\AcceptTerm', $term);
    }
    
    public function providesInvalidTypeValues() {
        return array(
            array(1),
            array(new StdClass),
            array(true)
        );
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Http\Negotiation\AcceptTerm::__toString
     */
    public function testToString($pos, $type, $quality, $explicit) {
        $term = new AcceptTerm($pos, $type, $quality, $explicit);
        $this->assertEquals($type, $term->__toString());
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Http\Negotiation\AcceptTerm::getPosition
     */
    public function testGetPosition($pos, $type, $quality, $explicit) {
        $term = new AcceptTerm($pos, $type, $quality, $explicit);
        $this->assertEquals($pos, $term->getPosition());
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Http\Negotiation\AcceptTerm::getType
     */
    public function testGetType($pos, $type, $quality, $explicit) {
        $term = new AcceptTerm($pos, $type, $quality, $explicit);
        $this->assertEquals($type, $term->getType());
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Http\Negotiation\AcceptTerm::getQuality
     */
    public function testGetQuality($pos, $type, $quality, $explicit) {
        $term = new AcceptTerm($pos, $type, $quality, $explicit);
        $this->assertEquals($quality, $term->getQuality());
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Http\Negotiation\AcceptTerm::hasExplicitQuality
     */
    public function testHasExplicitQuality($pos, $type, $quality, $explicit) {
        $term = new AcceptTerm($pos, $type, $quality, $explicit);
        $this->assertEquals($explicit, $term->hasExplicitQuality());
    }
}
