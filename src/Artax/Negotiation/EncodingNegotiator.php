<?php

namespace Artax\Negotiation;

use Artax\Negotiation\Terms\Term;

class EncodingNegotiator extends AbstractNegotiator {
    
    /**
     * Negotiates the appropriate content-encoding from a raw Accept-Encoding header
     * 
     * ```
     * <?php
     * use Artax\Negotiation\EncodingNegotiator;
     * 
     * $rawHeader = 'gzip, deflate, identity';
     * $availableTypes = array(
     *     'gzip' => 1,
     *     'identity' => 0.9
     * );
     * 
     * $negotiator = new EncodingNegotiator();
     * $negotiatedEncoding = $negotiator->negotiate($rawHeader, $availableTypes);
     * echo $negotiatedEncoding; // gzip
     * ```
     * 
     * @param string $rawAcceptEncodingHeader A raw Accept-Encoding HTTP header value
     * @param array $availableEncodings An array of available encodings
     * @throws \Spl\ValueException On invalid available encodings array
     * @throws NotAcceptableException If no acceptable encodings exist
     * @return string Returns the negotiated content encoding
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
                $negotiatedQval = $this->negotiateQualityValue($term->getQuality(), $qval);
                $hasExplicitQval = $term->hasExplicitQuality();
                $scratchTerms[] = new Term(
                    $term->getPosition(),
                    $type,
                    $negotiatedQval,
                    $hasExplicitQval
                );
            } elseif ($wildcardAllowed) {
                $negotiatedQval = $this->negotiateQualityValue($asteriskQval, $qval);
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