<?php
/**
 * ContentTypeNegotiator Class File
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Negotiation\Negotiators;

use InvalidArgumentException,
    Artax\MimeType,
    Artax\MimeTypeFactory,
    Artax\Negotiation\Parsers\HeaderParser,
    Artax\Negotiation\NotAcceptableException;

/**
 * Negotiate a response content-type from a raw Accept header
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ContentTypeNegotiator extends BaseNegotiator {
    
    /**
     * @var MimeTypeFactory
     */
    private $mimeTypeFactory;
    
    /**
     * @param HeaderParser $parser
     * @return void
     */
    public function __construct(
        HeaderParser $parser,
        MimeTypeFactory $mimeTypeFactory = null
    ) {
        $this->parser = $parser;
        $this->mimeTypeFactory = $mimeTypeFactory ?: new MimeTypeFactory;
    }
    
    /**
     * @param string $rawAcceptHeader
     * @param array $availableMimeTypes
     * @return string
     * @throws InvalidArgumentException
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
     * @throws InvalidArgumentException
     */
    private function convertStringsToMimeTypes(array $arrayOfMimeTypeStrings) {
        $self = $this;
        return array_map(function($mimeStr) use ($self) {
            return $self->mimeTypeFactory->make($mimeStr);
        }, $arrayOfMimeTypeStrings);
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
