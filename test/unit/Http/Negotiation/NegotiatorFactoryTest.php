<?php

use Artax\Http\Negotiation\NegotiatorFactory;

class NegotiatorFactoryTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\Negotiation\NegotiatorFactory::make
     * @expectedException Spl\DomainException
     */
    public function testMakeThrowsExceptionOnInvalidNegotiatorTypeValue() {
        $negotiatorFactory = new NegotiatorFactory;
        $negotiatorFactory->make('invalidNegotiator');
    }
    
    /**
     * @covers Artax\Http\Negotiation\NegotiatorFactory::make
     */
    public function testMakeReturnsExpectedHeaderNegotiatorInstance() {
        $negotiatorFactory = new NegotiatorFactory;
        
        $this->assertInstanceOf('Artax\\Http\\Negotiation\\Negotiators\\CharsetNegotiator',
            $negotiatorFactory->make('charset')
        );
        $this->assertInstanceOf('Artax\\Http\\Negotiation\\Negotiators\\ContentTypeNegotiator',
            $negotiatorFactory->make('Content-Type')
        );
        $this->assertInstanceOf('Artax\\Http\\Negotiation\\Negotiators\\ContentTypeNegotiator',
            $negotiatorFactory->make('contentType')
        );
        $this->assertInstanceOf('Artax\\Http\\Negotiation\\Negotiators\\LanguageNegotiator',
            $negotiatorFactory->make('language')
        );
        $this->assertInstanceOf('Artax\\Http\\Negotiation\\Negotiators\\EncodingNegotiator',
            $negotiatorFactory->make('encoding')
        );
    }
}
