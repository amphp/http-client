<?php

namespace Artax\Http\Negotiation\Negotiators;

use Artax\Http\Negotiation\MimeType,
    Artax\Http\Negotiation\Parsers\HeaderParser,
    Artax\Http\Negotiation\NotAcceptableException;

class ContentTypeNegotiator extends BaseNegotiator {
    
    /**
     * @param HeaderParser $parser
     * @return void
     */
    public function __construct(HeaderParser $parser) {
        $this->parser = $parser;
    }
    
    /**
     * @param string $rawAcceptHeader
     * @param array $availableMimeTypes
     * @return string
     * @throws Spl\ValueException
     * @throws NotAcceptableException
     */
    public function negotiate($rawAcceptHeader, array $availableMimeTypes) {
        $available = $this->convertStringsToMimeTypes($availableMimeTypes);
        
        // rfc2616-sec14.1: "If no Accept header field is present, then it is assumed that
        // the client accepts all media types."
        if (!$rawAcceptHeader) {
            return $available[0]->__toString();
        }
        
        $terms = $this->parser->parse($rawAcceptHeader);
        list($accept, $reject) = $this->getAcceptablesFromParsedTerms($terms);
        
        foreach ($accept as $mediaRangeTerm) {
            foreach ($available as $mimeType) {
                if ($mediaRangeTerm->rangeMatches($mimeType)
                    && !$this->isRejected($reject, $mimeType)
                ) {
                    return $mimeType->__toString();
                }
            }
        }
        
        throw new NotAcceptableException(
            "No available content-types match `Accept: $rawAcceptHeader`. Available types: " .
            '[' . implode('|', $available) . ']'
        );
    }
    
    /**
     * @param array $arrayOfMimeTypeStrings
     * @return array
     * @throws Spl\ValueException
     */
    private function convertStringsToMimeTypes(array $arrayOfMimeTypeStrings) {        
        $mimeTypeObjects = array();
        
        foreach ($arrayOfMimeTypeStrings as $mimeStr) {
            $mimeTypeObjects[] = new MimeType($mimeStr);
        }
        
        return $mimeTypeObjects;
    }
    
    /**
     * @param array $rejectableMediaRangeTerms
     * @param MimeType $mimeType
     * @return bool
     */
    private function isRejected(array $rejectableMediaRangeTerms, MimeType $mimeType) {   
        foreach ($rejectableMediaRangeTerms as $mediaRangeTerm) {
            if ($mediaRangeTerm->rangeMatches($mimeType)) {
                return true;
            }
        }
        return false;
    }
}
