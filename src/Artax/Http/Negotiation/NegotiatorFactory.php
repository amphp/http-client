<?php

namespace Artax\Http\Negotiation;

use Spl\DomainException,
    Artax\Http\Negotiation\Negotiators\ContentTypeNegotiator,
    Artax\Http\Negotiation\Negotiators\CharsetNegotiator,
    Artax\Http\Negotiation\Negotiators\LanguageNegotiator,
    Artax\Http\Negotiation\Negotiators\EncodingNegotiator,
    Artax\Http\Negotiation\Parsers\AcceptParser,
    Artax\Http\Negotiation\Parsers\AcceptCharsetParser,
    Artax\Http\Negotiation\Parsers\AcceptLanguageParser,
    Artax\Http\Negotiation\Parsers\AcceptEncodingParser;

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
