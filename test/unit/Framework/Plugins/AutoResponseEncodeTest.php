<?php

use Artax\Framework\Plugins\AutoResponseEncode,
    Artax\Http\StdResponse;

class AutoResponseEncodeTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Plugins\AutoResponseEncode::__construct
     */
    public function testBeginsEmpty() {
        $request = $this->getMock('Artax\\Http\\Request');
        $mediaRangeFactory = $this->getMock('Artax\\MediaRangeFactory');
        $mimeTypeFactory = $this->getMock('Artax\\MimeTypeFactory');
        $codecFactory = $this->getMock('Artax\\Encoding\\CodecFactory');
        
        $plugin = new AutoResponseEncode(
            $request,
            $mediaRangeFactory,
            $mimeTypeFactory,
            $codecFactory
        );
        
        $this->assertInstanceOf('Artax\\Framework\\Plugins\\AutoResponseEncode', $plugin);
    }
    
    /**
     * @covers Artax\Framework\Plugins\AutoResponseEncode::__invoke
     */
    public function testMagicInvokeCallsPrimaryWorkMethod() {
        $request = $this->getMock('Artax\\Http\\Request');
        $mediaRangeFactory = $this->getMock('Artax\\MediaRangeFactory');
        $mimeTypeFactory = $this->getMock('Artax\\MimeTypeFactory');
        $codecFactory = $this->getMock('Artax\\Encoding\\CodecFactory');
        
        $plugin = $this->getMock(
            'Artax\\Framework\\Plugins\\AutoResponseEncode',
            array('encodeResponseBody'),
            array($request, $mediaRangeFactory, $mimeTypeFactory, $codecFactory)
        );
        
        $response = $this->getMock('Artax\\Http\\Response');
        
        $plugin->expects($this->once())
               ->method('encodeResponseBody')
               ->with($response);
        
        $plugin($response);
    }
    
    /**
     * @covers Artax\Framework\Plugins\AutoResponseEncode::encodeResponseBody
     */
    public function testEncodeResponseBodyReturnsIfRequiredResponseHeadersMissing() {
        $request = $this->getMock('Artax\\Http\\Request');
        $mediaRangeFactory = $this->getMock('Artax\\MediaRangeFactory');
        $mimeTypeFactory = $this->getMock('Artax\\MimeTypeFactory');
        $codecFactory = $this->getMock('Artax\\Encoding\\CodecFactory');
        
        $plugin = new AutoResponseEncode(
            $request,
            $mediaRangeFactory,
            $mimeTypeFactory,
            $codecFactory
        );
        
        $response = $this->getMock('Artax\\Http\\Response');
        $response->expects($this->once())
                 ->method('hasHeader')
                 ->with($this->logicalOr('Content-Type', 'Content-Encoding'))
                 ->will($this->returnValue(false));
        
        $this->assertNull($plugin($response));
    }
    
    /**
     * @covers Artax\Framework\Plugins\AutoResponseEncode::encodeResponseBody
     */
    public function testEncodeResponseBodyReturnsIfResponseContentEncodingHeaderNotSupported() {
        $request = $this->getMock('Artax\\Http\\Request');
        $mediaRangeFactory = $this->getMock('Artax\\MediaRangeFactory');
        $mimeTypeFactory = $this->getMock('Artax\\MimeTypeFactory');
        $codecFactory = $this->getMock('Artax\\Encoding\\CodecFactory');
        
        $plugin = new AutoResponseEncode(
            $request,
            $mediaRangeFactory,
            $mimeTypeFactory,
            $codecFactory
        );
        
        $response = $this->getMock('Artax\\Http\\Response');
        $response->expects($this->exactly(2))
                 ->method('hasHeader')
                 ->with($this->logicalOr('Content-Type', 'Content-Encoding'))
                 ->will($this->returnValue(true));
        $response->expects($this->once())
                 ->method('getHeader')
                 ->with('Content-Encoding')
                 ->will($this->returnValue('zip'));
        
        $this->assertNull($plugin($response));
    }
    
    /**
     * @covers Artax\Framework\Plugins\AutoResponseEncode::encodeResponseBody
     * @covers Artax\Framework\Plugins\AutoResponseEncode::getEncodableMimeType
     */
    public function testEncodeResponseBodyReturnsIfBrowserQuirksReturnsFalse() {
        $request = $this->getMock('Artax\\Http\\Request');
        $mediaRangeFactory = new Artax\MediaRangeFactory();
        $mimeTypeFactory = new Artax\MimeTypeFactory();
        $codecFactory = $this->getMock('Artax\\Encoding\\CodecFactory');
        
        $plugin = $this->getMock(
            'Artax\\Framework\\Plugins\\AutoResponseEncode',
            array('accountForBrowserQuirks'),
            array($request, $mediaRangeFactory, $mimeTypeFactory, $codecFactory)
        );
        
        $response = new StdResponse();
        $response->setRawHeader('Content-Type: gobbledygook');
        $response->setRawHeader('Content-Encoding: gzip');
        
        $this->assertNull($plugin($response));
        
        $plugin->expects($this->once())
               ->method('accountForBrowserQuirks')
               ->will($this->returnValue(false));
        
        $response->setRawHeader('Content-Type: text/html; charset=ISO-8859-4');
        $this->assertNull($plugin($response));
    }
}





















