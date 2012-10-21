<?php

namespace Artax\Negotiation;

use Artax\Negotiation\Terms\Term;

class EncodingNegotiator extends AbstractNegotiator {
    
    /**
     * Negotiates the appropriate content-encoding from a raw `Accept-Encoding` header
     * 
     * @param string $rawAcceptEncodingHeader
     * @param array $available
     * @throws \Spl\ValueException
     * @throws NotAcceptableException
     * @return string
     */
    public function negotiate($rawAcceptEncodingHeader, array $availableEncodings) {
        $this->validateQualityValues($availableEncodings);
        
        // Order available types from highest to lowest preference
        arsort($availableEncodings);
        
        // rfc2616-sec3.5: "All content-coding values are case-insensitive."
        $availableKeys = array_map('strtolower', array_keys($availableEncodings));
        $availableVals = array_values($availableEncodings);
        $availableEncodings = array_combine($availableKeys, $availableVals);
        $rawAcceptEncodingHeader = strtolower($rawAcceptEncodingHeader);
        
        // rfc2616-sec14.3:
        // "If no Accept-Encoding field is present in a request, the server MAY assume that
        // the client will accept any content coding. In this case, if "identity" is one of
        // the available content-codings, then the server SHOULD use the "identity" content-coding,
        // unless it has additional information that a different content-coding is meaningful
        // to the client."
        if (!$rawAcceptEncodingHeader) {
            return 'identity';
        }
        
        $parsedHeaderTerms = $this->parseTermsFromHeader($rawAcceptEncodingHeader);
        
        if ($negotiatedType = $this->doNegotiation($availableEncodings, $parsedHeaderTerms)) {
            return $negotiatedType;
        } else {
            throw new NotAcceptableException(
                "No available encodings match `Accept-Encoding: $rawAcceptEncodingHeader`. " .
                'Available set: [' . implode('|', $availableKeys) . ']'
            );
        }
    }
    
    /**
     * @param array $availableTypes
     * @param array $parsedHeaderTerms
     * @return string Returns negotiated encoding or NULL if no acceptable values
     */
    private function doNegotiation(array $availableTypes, array $parsedHeaderTerms) {
        $scratchTerms = array();
        
        // As per rfc2616-sec14.3:
        // The special "*" symbol in an Accept-Encoding field matches any
        // available content-coding not explicitly listed in the header
        // field.
        $wildcardAllowed = false;
        $asteriskTermKey = array_search('*', $parsedHeaderTerms);
        if (false !== $asteriskTermKey) {
            $wildcardAllowed = true;
            $asteriskQval = $parsedHeaderTerms[$asteriskTermKey]->getQuality();
            $asteriskIsExplicit = $parsedHeaderTerms[$asteriskTermKey]->hasExplicitQuality();
        }
        
        foreach ($availableTypes as $type => $qval) {
            $termKey = array_search($type, $parsedHeaderTerms);
            if (false !== $termKey) {
                $term = $parsedHeaderTerms[$termKey];
                $negotiatedQval = round(
                    ($qval * $term->getQuality()),
                    Negotiator::QVAL_SIGNIFICANT_DIGITS
                );
                $hasExplicitQval = $term->hasExplicitQuality();
                $scratchTerms[] = new Term(
                    $term->getPosition(),
                    $type,
                    $negotiatedQval,
                    $hasExplicitQval
                );
            } elseif ($wildcardAllowed) {
                $negotiatedQval = round(
                    ($qval * $asteriskQval),
                    Negotiator::QVAL_SIGNIFICANT_DIGITS
                );
                $scratchTerms[] = new Term(
                    $asteriskTermKey,
                    $type,
                    $negotiatedQval,
                    $asteriskIsExplicit
                );
            }
        }
        
        $scratchTerms = $this->filterRejectedTerms($scratchTerms);
        $scratchTerms = $this->sortTermsByPreference($scratchTerms);
        
        if ($scratchTerms) {
            return current($scratchTerms)->getType();
        } else {
            return null;
        }
        
        return null;
    }
}