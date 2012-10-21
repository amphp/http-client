<?php

namespace Artax\Negotiation;

/**
 * Aggregates access to the various HTTP content negotiators in one place
 */
class CompositeNegotiator {
    
    /**
     * @var NegotiatorFactory
     */
    private $negotiatorFactory;
    
    /**
     * @param NegotiatorFactory $negotiatorFactory
     * @return void
     */
    public function __construct(NegotiatorFactory $negotiatorFactory) {
        $this->negotiatorFactory = $negotiatorFactory;
    }
    
    /**
     * Negotiate an appropriate content type from a raw HTTP Accept header
     * 
     * @param string $rawAcceptHeader
     * @return string
     * @throws NotAcceptableException
     */
    public function negotiateContentType($rawAcceptHeader, array $availableContentTypes) {
        $negotiator = $this->negotiatorFactory->make('ContentType');
        return $negotiator->negotiate($rawAcceptHeader, $availableContentTypes);
    }
    
    /**
     * Negotiate an appropriate character set from a raw HTTP Accept-Charset header
     * 
     * @param string $rawAcceptCharsetHeader
     * @return string
     * @throws NotAcceptableException
     */
    public function negotiateCharset($rawAcceptCharsetHeader, array $availableCharsets) {
        $negotiator = $this->negotiatorFactory->make('Charset');
        return $negotiator->negotiate($rawAcceptCharsetHeader, $availableCharsets);
    }
    
    /**
     * Negotiate an appropriate language from a raw HTTP Accept-Language header
     * 
     * @param string $rawAcceptLanguageHeader
     * @return string
     * @throws NotAcceptableException
     */
    public function negotiateLanguage($rawAcceptLanguageHeader, array $availableLanguages) {
        $negotiator = $this->negotiatorFactory->make('Language');
        return $negotiator->negotiate($rawAcceptLanguageHeader, $availableLanguages);
    }
    
    /**
     * Negotiate an appropriate content encoding from a raw HTTP Accept-Encoding header
     * 
     * @param string $rawAcceptEncodingHeader
     * @return string
     * @throws NotAcceptableException
     */
    public function negotiateEncoding($rawAcceptEncodingHeader, array $availableEncodings) {
        $negotiator = $this->negotiatorFactory->make('Encoding');
        return $negotiator->negotiate($rawAcceptEncodingHeader, $availableEncodings);
    }
}
