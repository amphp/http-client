<?php
/**
 * AutoResponseEncode Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Plugins
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Plugins;

use InvalidArgumentException,
    Artax\Http\Request,
    Artax\Http\Response,
    Artax\MediaRangeFactory,
    Artax\MimeTypeFactory,
    Artax\Encoding\CodecFactory,
    Artax\Framework\Config\Config;

/**
 * Automatically encodes response body for supported Content-Encodings and Content-Types
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Plugins
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class AutoResponseEncode {
    
    private $request;
    private $mediaRangeFactory;
    private $mimeTypeFactory;
    private $codecFactory;
    private $encodableMediaRanges = array();
    private $availableCodecs = array('gzip', 'deflate');
    
    public function __construct(
        Request $request,
        MediaRangeFactory $mediaRangeFactory,
        MimeTypeFactory $mimeTypeFactory,
        CodecFactory $codecFactory,
        array $encodableMediaRanges
    ) {
        $this->request = $request;
        $this->mediaRangeFactory = $mediaRangeFactory;
        $this->mimeTypeFactory = $mimeTypeFactory;
        $this->codecFactory = $codecFactory;
        $this->setEncodableMediaRanges($encodableMediaRanges);
    }
    
    protected function setEncodableMediaRanges(array $encodableMediaRanges) {
        foreach ($encodableMediaRanges as $mediaRangeStr) {
            $this->encodableMediaRanges[] = $this->mediaRangeFactory->make($mediaRangeStr);
        }
    }
    
    public function __invoke(Response $response) {
        $this->encodeResponseBody($response);
    }
    
    public function encodeResponseBody(Response $response) {
        if (!($response->hasHeader('Content-Type') && $response->hasHeader('Content-Encoding'))) {
            return;
        }
        
        $encoding = strtolower($response->getHeader('Content-Encoding'));
        if (!in_array($encoding, $this->availableCodecs)) {
            return;
        }
        
        $mimeType = $this->getEncodableMimeType($response);
        if (!$mimeType) {
            return;
        }
        
        if (!$this->accountForBrowserQuirks($mimeType, $encoding)) {
            $response->removeHeader('Content-Encoding');
            return;
        }
        
        $codec = $this->codecFactory->make($encoding);
        $encoded = $codec->encode($response->getBody());
        $response->setHeader('Content-Length', strlen($encoded));
        $response->setBody($encoded);
        
        // rfc-rfc2616-sec14.44:
        // "An HTTP/1.1 server SHOULD include a Vary header field with any cacheable 
        // response that is subject to server-driven negotiation."
        $this->setVaryHeader($response);
    }
    
    protected function getEncodableMimeType(Response $response) {
        $rawHeader = $response->getHeader('Content-Type');
        
        if (strpos($rawHeader, ';')) {
            $parts = explode(';', $rawHeader);
            $contentType = trim($parts[0]);
        } else {
            $contentType = $rawHeader;
        }
        
        try {
            $mimeType = $this->mimeTypeFactory->make($contentType);
        } catch (InvalidArgumentException $e) {
            return;
        }
        
        foreach ($this->encodableMediaRanges as $mediaRange) {
            if ($mediaRange->matches($mimeType)) {
                return $mimeType;
            }
        }
    }
    
    protected function accountForBrowserQuirks($mimeType, $encoding) {
        if (!$this->request->hasHeader('User-Agent')) {
            return true;
        }
        
        $userAgent = $this->request->getHeader('User-Agent');
        
        // Workaround for IE bug: http://support.microsoft.com/kb/323308
        if (preg_match('/MSIE\s*([5678])?/', $userAgent, $match)) {
            if (isset($match[1])
                && $this->request->getScheme() == 'https'
                && $this->request->hasHeader('Cache-Control')
            ) {
                $cacheControl = strtolower($this->request->getHeader('Cache-Control'));
                return (!($cacheControl == 'no-store' || $cacheControl == 'no-cache'));
            }
            return true;
        }
        
        // Netscape 4.x has problems ... especially 4.06-4.08
        if (preg_match('{Mozilla/4(?:\.0([678]))?}', $userAgent, $match)) {
            if ($mimeType != 'text/html' || $encoding !== 'gzip' || isset($match[1])) {
                return false;
            }
        }
    }
    
    protected function setVaryHeader(Response $response) {
        $varyHeader = $response->hasHeader('Vary')
            ? $response->getHeader('Vary') . ',User-Agent'
            : 'Accept-Encoding,User-Agent';
        
        $response->setHeader('Vary', $varyHeader);
    }
}
