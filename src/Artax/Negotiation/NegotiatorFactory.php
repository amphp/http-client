<?php
/**
 * NegotiatorFactory Class File
 * 
 * PHP 5.3+
 * 
 * @category     Artax
 * @package      Negotiation
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax\Negotiation;

use DomainException,
    Artax\Negotiation\Negotiators\ContentTypeNegotiator,
    Artax\Negotiation\Negotiators\CharsetNegotiator,
    Artax\Negotiation\Negotiators\LanguageNegotiator,
    Artax\Negotiation\Negotiators\EncodingNegotiator,
    Artax\Negotiation\Parsers\AcceptParser,
    Artax\Negotiation\Parsers\AcceptCharsetParser,
    Artax\Negotiation\Parsers\AcceptLanguageParser,
    Artax\Negotiation\Parsers\AcceptEncodingParser;

/**
 * Generates header-specific content negotiators
 * 
 * @category     Artax
 * @package      Negotiation
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class NegotiatorFactory {
    
    /**
     * @param string $negotiatorType
     * @return mixed
     * @throws DomainException
     */
    public function make($negotiatorType) {
        $normalizedType = strtolower(str_replace('-', '', $negotiatorType));
        
        switch ($normalizedType) {
            case 'contenttype':
                return new ContentTypeNegotiator(new AcceptParser);
            case 'charset':
                return new CharsetNegotiator(new AcceptCharsetParser);
            case 'language':
                return new LanguageNegotiator(new AcceptLanguageParser);
            case 'encoding':
                return new EncodingNegotiator(new AcceptEncodingParser);
            default:
                throw new DomainException(
                    "Invalid Negotiator type specified: $negotiatorType"
                );
        }
    }
}
