<?php

use Artax\Encoding\CodecFactory;

class CodecFactoryTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Encoding\CodecFactory::make
     */
    public function testMakeReturnsGzipCodecOnGzipParamArgument() {
        $codecFactory = new CodecFactory;
        
        $codec = $codecFactory->make('gzip');
        $this->assertInstanceOf('Artax\\Encoding\\GzipCodec', $codec);
        
        $codec = $codecFactory->make('gZiP');
        $this->assertInstanceOf('Artax\\Encoding\\GzipCodec', $codec);
    }
    
    /**
     * @covers Artax\Encoding\CodecFactory::make
     */
    public function testMakeReturnsDeflateCodecOnDeflateParamArgument() {
        $codecFactory = new CodecFactory;
        
        $codec = $codecFactory->make('deflate');
        $this->assertInstanceOf('Artax\\Encoding\\DeflateCodec', $codec);
        
        $codec = $codecFactory->make('DEFLATE');
        $this->assertInstanceOf('Artax\\Encoding\\DeflateCodec', $codec);
    }
    
    /**
     * @covers Artax\Encoding\CodecFactory::make
     * @expectedException DomainException
     */
    public function testMakeThrowsExceptionOnInvalidCodecTypeArgument() {
        $codecFactory = new CodecFactory;
        $codec = $codecFactory->make('invalidArgument');
    }
}
