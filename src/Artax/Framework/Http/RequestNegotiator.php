<?php
/**
 * HTTP RequestNegotiator Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Http;

use LogicException,
    Artax\Http\Request,
    Artax\Http\Response,
    Artax\Negotiation\CompositeNegotiator;

/**
 * Negotiates response content for a given client request
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class RequestNegotiator {
    
    /**
     * @var CompositeNegotiator
     */
    private $negotiator;
    
    /**
     * @var array
     */
    private $contentTypes;
    
    /**
     * @var array
     */
    private $charsets;
    
    /**
     * @var array
     */
    private $languages;
    
    /**
     * @var array
     */
    private $encodings;
    
    /**
     * @param CompositeNegotiator $negotiator
     * @return void
     */
    public function __construct(CompositeNegotiator $negotiator) {
        $this->negotiator = $negotiator;
    }
    
    /**
     * @param Request $request
     * @return array
     * @throws LogicException
     * @throws NotAcceptableException
     */
    public function negotiate(Request $request) {
        if (!$this->contentTypes) {
            throw new LogicException(
                'No available content types defined (e.g. `text/html`, `application/json`, etc.)'
            );
        } elseif (!$this->charsets) {
            throw new LogicException(
                'No available charsets defined (e.g. `iso-8859-1`, `utf-8`, etc.)'
            );
        } elseif (!$this->languages) {
            throw new LogicException(
                'No available languages defined (e.g. `en`, `en-us`, `da`, `fr`, etc.)'
            );
        }
        
        return array(
            'contentType' => $this->negotiateContentType($request),
            'charset' => $this->negotiateCharset($request),
            'language' => $this->negotiateLanguage($request),
            'encoding' => $this->negotiateEncoding($request)
        );
    }
    
    /**
     * @param Request $request
     * @param Response $response
     * @return array
     * @throws LogicException
     * @throws NotAcceptableException
     */
    public function negotiateAndApply(Request $request, Response $response) {
        $negotiatedArr = $this->negotiate($request, $response);
        extract($negotiatedArr);
        
        $response->setHeader('Content-Type', "$contentType; charset=$charset");
        $response->setHeader('Content-Language', "$language");
        
        if ($encoding !== 'identity') {
            $response->setHeader('Content-Encoding', "$encoding");
        }
        
        // rfc-rfc2616-sec14.44:
        // "An HTTP/1.1 server SHOULD include a Vary header field with any cacheable 
        // response that is subject to server-driven negotiation."
        $response->setHeader('Vary', 'Accept,Accept-Charset,Accept-Language,Accept-Encoding');
        
        return $negotiatedArr;
    }
    
    /**
     * @param Request $request
     * @return string
     * @throws NotAcceptableException
     */
    private function negotiateContentType(Request $request) {
        $header = $request->hasHeader('Accept')
            ? $request->getHeader('Accept')
            : '';
        
        return $this->negotiator->negotiateContentType($header, $this->contentTypes);
    }
    
    /**
     * @param Request $request
     * @return string
     * @throws NotAcceptableException
     */
    private function negotiateCharset(Request $request) {
        $header = $request->hasHeader('Accept-Charset')
            ? $request->getHeader('Accept-Charset')
            : '';
        
        return $this->negotiator->negotiateCharset($header, $this->charsets);
    }
    
    /**
     * @param Request $request
     * @return string
     * @throws NotAcceptableException
     */
    private function negotiateLanguage(Request $request) {
        $header = $request->hasHeader('Accept-Language')
            ? $request->getHeader('Accept-Language')
            : '';
        
        return $this->negotiator->negotiateLanguage($header, $this->languages);
    }
    
    /**
     * @param Request $request
     * @return string
     * @throws NotAcceptableException
     */
    private function negotiateEncoding(Request $request) {
        $header = $request->hasHeader('Accept-Encoding')
            ? $request->getHeader('Accept-Encoding')
            : '';
        
        $encodings = $this->encodings ?: array('identity');
        
        return $this->negotiator->negotiateEncoding($header, $encodings);
    }
    
    /**
     * @param array $availableContentTypes
     * @return void
     */
    public function setAvailableContentTypes(array $availableContentTypes) {
        $this->contentTypes = $availableContentTypes;
    }
    
    /**
     * @param array $availableCharsets
     * @return void
     */
    public function setAvailableCharsets(array $availableCharsets) {
        $this->charsets = $availableCharsets;
    }
    
    /**
     * @param array $availableLanguages
     * @return void
     */
    public function setAvailableLanguages(array $availableLanguages) {
        $this->languages = $availableLanguages;
    }
    
    /**
     * @param array $availableEncodings
     * @return void
     */
    public function setAvailableEncodings(array $availableEncodings) {
        $this->encodings = $availableEncodings;
    }
}
