<?php

use Artax\Negotiation\NegotiatorFactory;

class NegotiatorFactoryTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Negotiation\NegotiatorFactory::make
     * @expectedException Spl\DomainException
     */
    public function testMakeThrowsExceptionOnInvalidNegotiatorTypeValue() {
        $negotiatorFactory = new NegotiatorFactory;
        $negotiatorFactory->make('invalidNegotiator');
    }
    
    /**
     * @covers Artax\Negotiation\NegotiatorFactory::make
     */
    public function testMakeReturnsExpectedHeaderNegotiatorInstance() {
        $negotiatorFactory = new NegotiatorFactory;
        
        $this->assertInstanceOf('Artax\\Negotiation\\CharsetNegotiator',
            $negotiatorFactory->make('charset')
        );
        $this->assertInstanceOf('Artax\\Negotiation\\ContentTypeNegotiator',
            $negotiatorFactory->make('Content-Type')
        );
        $this->assertInstanceOf('Artax\\Negotiation\\ContentTypeNegotiator',
            $negotiatorFactory->make('contentType')
        );
        $this->assertInstanceOf('Artax\\Negotiation\\LanguageNegotiator',
            $negotiatorFactory->make('language')
        );
        $this->assertInstanceOf('Artax\\Negotiation\\EncodingNegotiator',
            $negotiatorFactory->make('encoding')
        );
    }
}
