<?php

use ArtaxPlugins\ResponseEncoder,
    Artax\Http\StdResponse;

require dirname(__DIR__) . '/src/ResponseEncoder.php';

class ResponseEncoderTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers ArtaxPlugins\ResponseEncoder::__construct
     * @covers ArtaxPlugins\ResponseEncoder::setCustomMediaRanges
     */
    public function testBeginsEmpty() {
        $request = $this->getMock('Artax\\Http\\Request');
        $mediaRangeFactory = $this->getMock('Artax\\MediaRangeFactory');
        $mimeTypeFactory = $this->getMock('Artax\\MimeTypeFactory');
        $codecFactory = $this->getMock('Artax\\Encoding\\CodecFactory');
        $appConfig = $this->getMock('Artax\\Framework\\Configuration\\AppConfig');
        
        $plugin = new ResponseEncoder(
            $request,
            $mediaRangeFactory,
            $mimeTypeFactory,
            $codecFactory,
            $appConfig
        );
        
        $this->assertInstanceOf('ArtaxPlugins\\ResponseEncoder', $plugin);
    }
    
    /**
     * @covers ArtaxPlugins\ResponseEncoder::__invoke
     */
    public function testMagicInvokeCallsPrimaryWorkMethod() {
        $request = $this->getMock('Artax\\Http\\Request');
        $mediaRangeFactory = $this->getMock('Artax\\MediaRangeFactory');
        $mimeTypeFactory = $this->getMock('Artax\\MimeTypeFactory');
        $codecFactory = $this->getMock('Artax\\Encoding\\CodecFactory');
        $appConfig = $this->getMock('Artax\\Framework\\Configuration\\AppConfig');
        
        $plugin = $this->getMock(
            'ArtaxPlugins\\ResponseEncoder',
            array('encode'),
            array($request, $mediaRangeFactory, $mimeTypeFactory, $codecFactory, $appConfig)
        );
        
        $response = $this->getMock('Artax\\Http\\Response');
        
        $plugin->expects($this->once())
               ->method('encode')
               ->with($response);
        
        $plugin($response);
    }
    
    /**
     * @covers ArtaxPlugins\ResponseEncoder::encode
     */
    public function testEncodeResponseBodyReturnsIfRequiredResponseHeadersMissing() {
        $request = $this->getMock('Artax\\Http\\Request');
        $mediaRangeFactory = $this->getMock('Artax\\MediaRangeFactory');
        $mimeTypeFactory = $this->getMock('Artax\\MimeTypeFactory');
        $codecFactory = $this->getMock('Artax\\Encoding\\CodecFactory');
        $appConfig = $this->getMock('Artax\\Framework\\Configuration\\AppConfig');
        
        $plugin = new ResponseEncoder(
            $request,
            $mediaRangeFactory,
            $mimeTypeFactory,
            $codecFactory,
            $appConfig
        );
        
        $response = $this->getMock('Artax\\Http\\Response');
        $response->expects($this->once())
                 ->method('hasHeader')
                 ->with($this->logicalOr('Content-Type', 'Content-Encoding'))
                 ->will($this->returnValue(false));
        
        $this->assertNull($plugin($response));
    }
    
    /**
     * @covers ArtaxPlugins\ResponseEncoder::encode
     */
    public function testEncodeResponseBodyReturnsIfResponseContentEncodingHeaderNotSupported() {
        $request = $this->getMock('Artax\\Http\\Request');
        $mediaRangeFactory = $this->getMock('Artax\\MediaRangeFactory');
        $mimeTypeFactory = $this->getMock('Artax\\MimeTypeFactory');
        $codecFactory = $this->getMock('Artax\\Encoding\\CodecFactory');
        $appConfig = $this->getMock('Artax\\Framework\\Configuration\\AppConfig');
        
        $plugin = new ResponseEncoder(
            $request,
            $mediaRangeFactory,
            $mimeTypeFactory,
            $codecFactory,
            $appConfig
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
     * @covers ArtaxPlugins\ResponseEncoder::encode
     * @covers ArtaxPlugins\ResponseEncoder::getEncodableMimeType
     */
    public function testEncodeResponseBodyReturnsIfBrowserQuirksReturnsFalse() {
        $request = $this->getMock('Artax\\Http\\Request');
        $mediaRangeFactory = new Artax\MediaRangeFactory();
        $mimeTypeFactory = new Artax\MimeTypeFactory();
        $codecFactory = $this->getMock('Artax\\Encoding\\CodecFactory');
        $appConfig = $this->getMock('Artax\\Framework\\Configuration\\AppConfig');
        
        $plugin = $this->getMock(
            'ArtaxPlugins\\ResponseEncoder',
            array('accountForBrowserQuirks'),
            array($request, $mediaRangeFactory, $mimeTypeFactory, $codecFactory, $appConfig)
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
    
    /**
     * @covers ArtaxPlugins\ResponseEncoder::encode
     * @covers ArtaxPlugins\ResponseEncoder::setVaryHeader
     */
    public function testEncodeResponseBodyAppliesEncodingAndRelevantHeaders() {
        $request = $this->getMock('Artax\\Http\\Request');
        $mediaRangeFactory = new Artax\MediaRangeFactory();
        $mimeTypeFactory = new Artax\MimeTypeFactory();
        $codecFactory = $this->getMock('Artax\\Encoding\\CodecFactory');
        $appConfig = $this->getMock('Artax\\Framework\\Configuration\\AppConfig');
        
        $plugin = $this->getMock(
            'ArtaxPlugins\\ResponseEncoder',
            array('accountForBrowserQuirks'),
            array($request, $mediaRangeFactory, $mimeTypeFactory, $codecFactory, $appConfig)
        );
        
        $response = new StdResponse();
        $body = 'TEST BODY TEXT';
        $response->setBody($body);
        $response->setRawHeader('Content-Type: text/html');
        $response->setRawHeader('Content-Encoding: gzip');
        
        $plugin->expects($this->once())
               ->method('accountForBrowserQuirks')
               ->will($this->returnValue(true));
        
        $codec = $this->getMock('Artax\\Encoding\\Codec');
        $codec->expects($this->once())
              ->method('encode')
              ->with($body);
        $codecFactory->expects($this->once())
                     ->method('make')
                     ->will($this->returnValue($codec));
        
        $this->assertNull($plugin($response));
        $this->assertEquals('Accept-Encoding,User-Agent', $response->getHeader('Vary'));
    }
    
    /**
     * @covers ArtaxPlugins\ResponseEncoder::__construct
     */
    public function testConstructorAssignsCustomMediaRangesIfSpecified() {
        $request = $this->getMock('Artax\\Http\\Request');
        $mediaRangeFactory = new Artax\MediaRangeFactory();
        $mimeTypeFactory = new Artax\MimeTypeFactory();
        $codecFactory = $this->getMock('Artax\\Encoding\\CodecFactory');
        $appConfig = $this->getMock('Artax\\Framework\\Configuration\\AppConfig');
        $appConfig->expects($this->once())
                  ->method('has')
                  ->with('Artax.ResponseEncoder.MediaRanges')
                  ->will($this->returnValue(true));
        $appConfig->expects($this->once())
                  ->method('get')
                  ->with('Artax.ResponseEncoder.MediaRanges')
                  ->will($this->returnValue('text/html'));
        
        $plugin = $this->getMock(
            'ArtaxPlugins\\ResponseEncoder',
            array('encode'),
            array($request, $mediaRangeFactory, $mimeTypeFactory, $codecFactory, $appConfig)
        );
    }
    
    /**
     * @covers ArtaxPlugins\ResponseEncoder::encode
     * @covers ArtaxPlugins\ResponseEncoder::accountForBrowserQuirks
     */
    public function testEncodeResponseAbortsIfUserAgentMatchesBrokenBrowser() {
        $this->markTestIncomplete();
        /*
        $request = $this->getMock('Artax\\Http\\Request');
        $mediaRangeFactory = new Artax\MediaRangeFactory();
        $mimeTypeFactory = new Artax\MimeTypeFactory();
        $codecFactory = $this->getMock('Artax\\Encoding\\CodecFactory');
        $encodableTypes = array('text/*', 'application/json', 'application/xml');
        
        $plugin = new ResponseEncoder(
            $request,
            $mediaRangeFactory,
            $mimeTypeFactory,
            $codecFactory,
            $encodableTypes
        );
        
        $response = $this->getMock('Artax\\Http\\Response');
        
        $request->expects($this->once())
                ->method('hasHeader')
                ->with('User-Agent')
                ->will($this->returnValue(false));
        
        $this->assertNull($plugin($response));
        
        $ie8 = 'Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
        
        $request->expects($this->once())
                ->method('hasHeader')
                ->with('User-Agent')
                ->will($this->returnValue(true));
        $request->expects($this->any())
                ->method('getHeader')
                ->with('User-Agent')
                ->will($this->returnValue($ie8));
        $request->expects($this->once())
                ->method('getScheme')
                ->will($this->returnValue('https'));
        $request->expects($this->once())
                ->method('hasHeader')
                ->with('Cache-Control')
                ->will($this->returnValue(true));
        $request->expects($this->any())
                ->method('getHeader')
                ->with('Cache-Control')
                ->will($this->returnValue('no-cache'));
        
        $this->assertNull($plugin($response));
        */
    }
}





















