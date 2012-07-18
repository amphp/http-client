<?php

use Artax\MimeTypeFactory;

class MimeTypeFactoryTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\MimeTypeFactory::make
     */
    public function testMakeReturnsMimeType() {
        $factory = new MimeTypeFactory;
        $mediaRange = 'text/html';
        $this->assertInstanceOf(
            'Artax\\MimeType',
            $factory->make($mediaRange)
        );
    }
}
