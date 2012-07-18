<?php

use Artax\MediaRange,
    Artax\Negotiation\MediaRangeTerm;

class MediaRangeTermTest extends PHPUnit_Framework_TestCase {
    
    public function providesValidTermConstructorArgs() {
        return array(
            array(0, new MediaRange('*/*'), 1.0, true),
            array(1, new MediaRange('text/html'), 1, false),
            array(1, new MediaRange('application/json'), 1, false),
            array(1, new MediaRange('text/*'), 0.7, true),
        );
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Negotiation\MediaRangeTerm::__construct
     */
    public function testConstructorInitializesProperties($pos, $type, $quality, $explicit) {
        $term = new MediaRangeTerm($pos, $type, $quality, $explicit);
        $this->assertInstanceOf('Artax\\Negotiation\\MediaRangeTerm', $term);
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Negotiation\MediaRangeTerm::__toString
     */
    public function testToString($pos, $type, $quality, $explicit) {
        $term = new MediaRangeTerm($pos, $type, $quality, $explicit);
        $this->assertEquals((string) $type, $term->__toString());
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Negotiation\MediaRangeTerm::getType
     */
    public function testGetType($pos, $type, $quality, $explicit) {
        $term = new MediaRangeTerm($pos, $type, $quality, $explicit);
        $this->assertEquals($type, $term->getType());
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Negotiation\MediaRangeTerm::getRangeTopLevelType
     */
    public function testGetRangeTopLevelType($pos, $type, $quality, $explicit) {
        $term = new MediaRangeTerm($pos, $type, $quality, $explicit);
        $this->assertEquals($type->getTopLevelType(), $term->getRangeTopLevelType());
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Negotiation\MediaRangeTerm::getRangeSubType
     */
    public function testGetRangeSubType($pos, $type, $quality, $explicit) {
        $term = new MediaRangeTerm($pos, $type, $quality, $explicit);
        $this->assertEquals($type->getSubType(), $term->getRangeSubType());
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Negotiation\MediaRangeTerm::getRangeSuffix
     */
    public function testGetRangeSuffix($pos, $type, $quality, $explicit) {
        $term = new MediaRangeTerm($pos, $type, $quality, $explicit);
        $this->assertEquals($type->getSuffix(), $term->getRangeSuffix());
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Negotiation\MediaRangeTerm::isRangeExperimental
     */
    public function testIsRangeExperimental($pos, $type, $quality, $explicit) {
        $term = new MediaRangeTerm($pos, $type, $quality, $explicit);
        $this->assertEquals($type->isExperimental(), $term->isRangeExperimental());
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Negotiation\MediaRangeTerm::rangeMatches
     */
    public function testRangeMatches($pos, $type, $quality, $explicit) {
        $term = new MediaRangeTerm($pos, $type, $quality, $explicit);
        $this->assertEquals($type->matches($type), $term->rangeMatches($type));
    }
}
