<?php

namespace Artax\Negotiation;

use Spl\ValueException,
    Artax\Negotiation\Terms\Term,
    Artax\Negotiation\Terms\ContentTypeTerm;

class ContentTypeNegotiator extends AbstractNegotiator {
    
    /**
     * Negotiates an appropriate content-type from an HTTP Accept header
     * 
     * ```
     * <?php
     * use Artax\Negotiation\ContentTypeNegotiator;
     * 
     * $rawHeader = 'text/html, application/xml;q=0.9, application/json;q=0.2, text/*;q=0.1';
     * $availableTypes = array(
     *     'text/html' => 1,
     *     'application/xml' => 0.2,
     *     'application/json' => 0.1
     * );
     * 
     * $negotiator = new ContentTypeNegotiator();
     * $negotiatedContentType = $negotiator->negotiate($rawHeader, $availableTypes);
     * echo $negotiatedCharset; // text/html
     * ```
     * 
     * @param string $rawAcceptHeader An HTTP Accept header value
     * @param array $availableTypes An array of available content-types and quality preferences
     * @throws \Spl\ValueException On invalid available types definition
     * @throws NotAcceptableException
     * @return string Returns the negotiated content-type
     */
    public function negotiate($rawAcceptHeader, array $availableTypes) {
        $this->validateQualityValues($availableTypes);
        
        // Order available types from highest to lowest preference
        arsort($availableTypes);
        
        // rfc2616-sec2.1:
        // "... Unless stated otherwise, the text is case-insensitive."
        $availableKeys = array_map('strtolower', array_keys($availableTypes));
        $availableVals = array_values($availableTypes);
        $availableTypes = array_combine($availableKeys, $availableVals);
        $rawAcceptHeader = strtolower($rawAcceptHeader);
        
        if (!$rawAcceptHeader) {
            return key($availableTypes);
        }
        
        $parsedHeaderTerms = $this->parseTermsFromHeader($rawAcceptHeader);
        
        if ($negotiatedType = $this->doNegotiation($availableTypes, $parsedHeaderTerms)) {
            return $negotiatedType;
        } else {
            throw new NotAcceptableException(
                "No available content-types match `Accept-ContentType: $rawAcceptHeader`. " .
                'Available set: [' . implode('|', $availableKeys) . ']'
            );
        }
    }
    
    /**
     * @param string $rawHeader
     * @return array[ContentTypeTerm]
     */
    protected function parseTermsFromHeader($rawHeader) {
        $terms = array();
        
        foreach (preg_split('/\s*,\s*/', $rawHeader) as $position => $term) {
            if (preg_match("{^(\S+)\s*;\s*(?:q|level)=([0-9\.]+)}i", $term, $match)) {
                $type = $match[1];
                $quality = $match[2];
                $hasExplicitQval = true;
            } else {
                $type = $term;
                $quality = 1;
                $hasExplicitQval = false;
            }
            
            try {
                $terms[] = new ContentTypeTerm($position, $type, $quality, $hasExplicitQval);
            } catch (ValueException $e) {
                // Ignore invalid media range header values from the raw HTTP Accept header
                continue;
            }
        }
        
        return $terms;
    }
    
    /**
     * @param array $availableTypes
     * @param array $parsedHeaderTerms
     * @return string Returns negotiated content-type or NULL if no acceptable values
     */
    private function doNegotiation(array $availableTypes, array $parsedHeaderTerms) {
        $scratchTerms = array();
        
        foreach ($availableTypes as $type => $qval) {
            $termKey = array_search($type, $parsedHeaderTerms);
            
            if (false !== $termKey) {
                $term = $parsedHeaderTerms[$termKey];
                $negotiatedQval = $this->negotiateQualityValue($term->getQuality(), $qval);
                $hasExplicitQval = $term->hasExplicitQuality();
                $scratchTerms[] = new Term(
                    $term->getPosition(),
                    $type,
                    $negotiatedQval,
                    $hasExplicitQval
                );
            } elseif ($rangeMatches = $this->getRangeMatchesForType($type, $parsedHeaderTerms)) {
                foreach ($rangeMatches as $term) {
                    $negotiatedQval = $this->negotiateQualityValue($term->getQuality(), $qval);
                    $hasExplicitQval = $term->hasExplicitQuality();
                    $scratchTerms[] = new Term(
                        $term->getPosition(),
                        $type,
                        $negotiatedQval,
                        $hasExplicitQval
                    );
                }
            }
        }
        
        $scratchTerms = $this->filterRejectedTerms($scratchTerms);
        $scratchTerms = $this->sortTermsByPreference($scratchTerms);
        
        if ($scratchTerms) {
            return current($scratchTerms)->getType();
        } else {
            return null;
        }
    }
    
    private function getRangeMatchesForType($type, $parsedHeaderTerms) {
        return array_filter($parsedHeaderTerms, function($t) use ($type) {
            return $t->rangeMatches($type);
        });
    }
}
