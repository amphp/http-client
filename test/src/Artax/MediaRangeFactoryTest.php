<?php

use Artax\MediaRangeFactory;

class MediaRangeFactoryTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\MediaRangeFactory::make
     */
    public function testMakeReturnsMediaRange() {
        $factory = new MediaRangeFactory;
        $mediaRange = '*/*';
        $this->assertInstanceOf('Artax\\MediaRange', $factory->make($mediaRange));
    }
}
