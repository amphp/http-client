<?php

use Artax\Negotiation\AcceptTermFactory;

class AcceptTermFactoryTest extends PHPUnit_Framework_TestCase {
    
    public function providesValidAcceptTermArgs() {
        return array(
            array(0, '*/*', 1.0, true),
            array(1, 'text/html', 1, false),
            array(1, new AcceptTermFactoryToStringTest('application/json'), 1, false),
            array(1, new AcceptTermFactoryToStringTest('en'), 0.7, true),
        );
    }
    
    /**
     * @dataProvider providesValidAcceptTermArgs
     * @covers Artax\Negotiation\AcceptTermFactory::make
     */
    public function testMakeCreatesAcceptTermInstances($pos, $type, $quality, $explicit) {
        $factory = new AcceptTermFactory;
        $term = $factory->make($pos, $type, $quality, $explicit);
        $this->assertInstanceOf('Artax\\Negotiation\\AcceptTerm', $term);
    }
}

class AcceptTermFactoryToStringTest {
    private $typeVal;
    public function __construct($typeVal) { $this->typeVal = $typeVal; }
    public function __toString() { return $this->typeVal; }
}
