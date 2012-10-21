<?php

use Artax\Negotiation\Terms\Term;

/**
 * @covers Artax\Negotiation\Terms\Term<extended>
 */
class TermTest extends PHPUnit_Framework_TestCase {
    
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
     * @covers Artax\Negotiation\Terms\Term::__construct
     */
    public function testConstructorInitializesProperties($pos, $type, $quality, $explicit) {
        $term = new Term($pos, $type, $quality, $explicit);
        $this->assertInstanceOf('Artax\\Negotiation\\Terms\\Term', $term);
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
     * @covers Artax\Negotiation\Terms\Term::__toString
     */
    public function testToString($pos, $type, $quality, $explicit) {
        $term = new Term($pos, $type, $quality, $explicit);
        $this->assertEquals($type, $term->__toString());
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Negotiation\Terms\Term::getPosition
     */
    public function testGetPosition($pos, $type, $quality, $explicit) {
        $term = new Term($pos, $type, $quality, $explicit);
        $this->assertEquals($pos, $term->getPosition());
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Negotiation\Terms\Term::getType
     */
    public function testGetType($pos, $type, $quality, $explicit) {
        $term = new Term($pos, $type, $quality, $explicit);
        $this->assertEquals($type, $term->getType());
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Negotiation\Terms\Term::getQuality
     */
    public function testGetQuality($pos, $type, $quality, $explicit) {
        $term = new Term($pos, $type, $quality, $explicit);
        $this->assertEquals($quality, $term->getQuality());
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Negotiation\Terms\Term::hasExplicitQuality
     */
    public function testHasExplicitQuality($pos, $type, $quality, $explicit) {
        $term = new Term($pos, $type, $quality, $explicit);
        $this->assertEquals($explicit, $term->hasExplicitQuality());
    }
}
