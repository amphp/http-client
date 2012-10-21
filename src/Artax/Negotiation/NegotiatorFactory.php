<?php

namespace Artax\Negotiation;

use Spl\DomainException;

class NegotiatorFactory {
    
    /**
     * @param string $negotiatorType
     * @return mixed
     * @throws \Spl\DomainException
     */
    public function make($negotiatorType) {
        $normalizedType = strtolower(str_replace('-', '', $negotiatorType));
        
        switch ($normalizedType) {
            case 'contenttype':
                return new ContentTypeNegotiator();
            case 'charset':
                return new CharsetNegotiator();
            case 'language':
                return new LanguageNegotiator();
            case 'encoding':
                return new EncodingNegotiator();
            default:
                throw new DomainException(
                    "Invalid Negotiator type specified: $negotiatorType"
                );
        }
    }
}
