<?php

use Artax\Negotiation\MediaRangeTermFactory;

class MediaRangeTermFactoryTest extends PHPUnit_Framework_TestCase {
    
    public function providesValidTermConstructorArgs() {
        return array(
            array(0, '*/*', 1.0, true),
            array(1, 'text/html', 1, false),
            array(1, 'application/json', 1, false),
            array(1, 'text/*', 0.7, true),
        );
    }
    
    /**
     * @dataProvider providesValidTermConstructorArgs
     * @covers Artax\Negotiation\MediaRangeTermFactory::__construct
     * @covers Artax\Negotiation\MediaRangeTermFactory::make
     */
    public function testMakeReturnsMediaRangeTerm($pos, $type, $quality, $explicit) {
        $factory = new MediaRangeTermFactory;
        $this->assertInstanceOf('Artax\\Negotiation\\MediaRangeTerm',
            $factory->make($pos, $type, $quality, $explicit)
        );
    }
}
