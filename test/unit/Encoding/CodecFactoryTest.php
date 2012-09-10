<?php

use Artax\Encoding\CodecFactory;

require_once __DIR__ . '/BaseEncodingTest.php';

class CodecFactoryTest extends BaseEncodingTest {
    
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
     * @expectedException Spl\DomainException
     */
    public function testMakeThrowsExceptionOnInvalidCodecTypeArgument() {
        $codecFactory = new CodecFactory;
        $codec = $codecFactory->make('invalidArgument');
    }
}
