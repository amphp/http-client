<?php

use Artax\Encoding\GzipCodec;

class GzipCodecTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Encoding\GzipCodec::encode
     * @covers Artax\Encoding\GzipCodec::doEncode
     */
    public function testEncodeReturnsGzipEncodedDataOnSucces() {
        $dataToBeEncoded = 'my test string for encoding';
        $gzipCodec = new GzipCodec;
        
        $encoded = $gzipCodec->encode($dataToBeEncoded);
        $this->assertEquals($dataToBeEncoded, gzdecode($encoded));
    }
    
    /**
     * @covers Artax\Encoding\GzipCodec::encode
     * @expectedException Artax\Encoding\CodecException
     */
    public function testEncodeThrowsCodecExceptionOnFailure() {
        $dataToBeEncoded = 'my test string for encoding';
        $gzipCodec = $this->getMock('Artax\\Encoding\\GzipCodec', array('doEncode'));
        $gzipCodec->expects($this->once())
                  ->method('doEncode')
                  ->will($this->returnValue(false))
                  ->with($dataToBeEncoded);
        
        $encoded = $gzipCodec->encode($dataToBeEncoded);
    }
    
    /**
     * @covers Artax\Encoding\GzipCodec::decode
     * @covers Artax\Encoding\GzipCodec::doDecode
     */
    public function testDecodeReturnsGzipDecodedDataOnSucces() {
        $unencodedData = 'my test string for encoding';
        $dataToBeDecoded = gzencode($unencodedData);
        $gzipCodec = new GzipCodec;
        
        $decoded = $gzipCodec->decode($dataToBeDecoded);
        $this->assertEquals($unencodedData, gzdecode($dataToBeDecoded));
    }
    
    /**
     * @covers Artax\Encoding\GzipCodec::decode
     * @expectedException Artax\Encoding\CodecException
     */
    public function testDecodeThrowsCodecExceptionOnFailure() {
        $dataToBeDecoded = gzencode('my test string for encoding');
        $gzipCodec = $this->getMock('Artax\\Encoding\\GzipCodec', array('doDecode'));
        $gzipCodec->expects($this->once())
                  ->method('doDecode')
                  ->will($this->returnValue(false))
                  ->with($dataToBeDecoded);
        
        $decoded = $gzipCodec->decode($dataToBeDecoded);
    }
}
