<?php
/**
 * CompositeNegotiator Class File
 * 
 * PHP 5.3+
 * 
 * @category     Artax
 * @package      Negotiation
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the base package directory
 * @version      ${project.version}
 */
namespace Artax\Negotiation;

/**
 * Aggregates access to the various HTTP content negotiators in one place
 * 
 * @category     Artax
 * @package      Negotiation
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
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
     * @param string $rawAcceptHeader
     * @return string
     * @throws NotAcceptableException
     */
    public function negotiateContentType($rawAcceptHeader, array $availableContentTypes) {
        $negotiator = $this->negotiatorFactory->make('ContentType');
        return $negotiator->negotiate($rawAcceptHeader, $availableContentTypes);
    }
    
    /**
     * @param string $rawAcceptCharsetHeader
     * @return string
     * @throws NotAcceptableException
     */
    public function negotiateCharset($rawAcceptCharsetHeader, array $availableCharsets) {
        $negotiator = $this->negotiatorFactory->make('Charset');
        return $negotiator->negotiate($rawAcceptCharsetHeader, $availableCharsets);
    }
    
    /**
     * @param string $rawAcceptLanguageHeader
     * @return string
     * @throws NotAcceptableException
     */
    public function negotiateLanguage($rawAcceptLanguageHeader, array $availableLanguages) {
        $negotiator = $this->negotiatorFactory->make('Language');
        return $negotiator->negotiate($rawAcceptLanguageHeader, $availableLanguages);
    }
    
    /**
     * @param string $rawAcceptEncodingHeader
     * @return string
     * @throws NotAcceptableException
     */
    public function negotiateEncoding($rawAcceptEncodingHeader, array $availableEncodings) {
        $negotiator = $this->negotiatorFactory->make('Encoding');
        return $negotiator->negotiate($rawAcceptEncodingHeader, $availableEncodings);
    }
}
