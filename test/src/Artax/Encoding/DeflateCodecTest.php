<?php

use Artax\Encoding\DeflateCodec;

class DeflateCodecTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Encoding\DeflateCodec::encode
     * @covers Artax\Encoding\DeflateCodec::doEncode
     */
    public function testEncodeReturnsGzipEncodedDataOnSucces() {
        $dataToBeEncoded = 'my test string for encoding';
        $deflateCodec = new DeflateCodec;
        
        $encoded = $deflateCodec->encode($dataToBeEncoded);
        $this->assertEquals($dataToBeEncoded, gzinflate($encoded));
    }
    
    /**
     * @covers Artax\Encoding\DeflateCodec::encode
     * @expectedException Artax\Encoding\CodecException
     */
    public function testEncodeThrowsCodecExceptionOnFailure() {
        $dataToBeEncoded = 'my test string for encoding';
        $deflateCodec = $this->getMock('Artax\\Encoding\\DeflateCodec', array('doEncode'));
        $deflateCodec->expects($this->once())
                  ->method('doEncode')
                  ->will($this->returnValue(false))
                  ->with($dataToBeEncoded);
        
        $encoded = $deflateCodec->encode($dataToBeEncoded);
    }
    
    /**
     * @covers Artax\Encoding\DeflateCodec::decode
     * @covers Artax\Encoding\DeflateCodec::doDecode
     */
    public function testDecodeReturnsGzipDecodedDataOnSucces() {
        $unencodedData = 'my test string for encoding';
        $dataToBeDecoded = gzdeflate($unencodedData);
        $deflateCodec = new DeflateCodec;
        
        $decoded = $deflateCodec->decode($dataToBeDecoded);
        $this->assertEquals($unencodedData, gzinflate($dataToBeDecoded));
    }
    
    /**
     * @covers Artax\Encoding\DeflateCodec::decode
     * @expectedException Artax\Encoding\CodecException
     */
    public function testDecodeThrowsCodecExceptionOnFailure() {
        $dataToBeDecoded = gzdeflate('my test string for encoding');
        $deflateCodec = $this->getMock('Artax\\Encoding\\DeflateCodec', array('doDecode'));
        $deflateCodec->expects($this->once())
                  ->method('doDecode')
                  ->will($this->returnValue(false))
                  ->with($dataToBeDecoded);
        
        $decoded = $deflateCodec->decode($dataToBeDecoded);
    }
}
