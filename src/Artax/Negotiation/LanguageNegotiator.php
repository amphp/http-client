<?php

namespace Artax\Negotiation;

use Artax\Negotiation\Terms\Term,
    Artax\Negotiation\Terms\LanguageTerm;

class LanguageNegotiator extends AbstractNegotiator {
    
    /**
     * Negotiates an appropriate language from an `Accept-Language` header
     * 
     * @param string $rawAcceptLanguageHeader
     * @param array $availableLangs An array of available languages and quality preferences
     * @throws \Spl\ValueException On invalid available types definition
     * @throws NotAcceptableException
     * @return string Returns the negotiated language
     */
    public function negotiate($rawAcceptLanguageHeader, array $availableLangs) {
        $this->validateQualityValues($availableLangs);
        
        // Order available types from highest to lowest preference
        arsort($availableLangs);
        
        // rfc2616-sec3.10: "... tags are case-insensitive."
        $availableKeys = array_map('strtolower', array_keys($availableLangs));
        $availableVals = array_values($availableLangs);
        $availableLangs = array_combine($availableKeys, $availableVals);
        $rawAcceptLanguageHeader = strtolower($rawAcceptLanguageHeader);
        
        // rfc2616-sec14.4:
        // "If no Accept-Language header is present in the request, the server SHOULD 
        // assume that all languages are equally acceptable."
        if (!$rawAcceptLanguageHeader) {
            return key($availableLangs);
        }
        
        $parsedHeaderTerms = $this->parseTermsFromHeader($rawAcceptLanguageHeader);
        
        if ($negotiatedLang = $this->doNegotiation($availableLangs, $parsedHeaderTerms)) {
            return $negotiatedLang;
        } else {
            throw new NotAcceptableException(
                "No available languages match `Accept-Language: $rawAcceptLanguageHeader`. " .
                'Available set: [' . implode('|', $availableKeys) . ']'
            );
        }
    }
    
    /**
     * @param string $rawHeader
     * @return array[LanguageTerm]
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
            
            $terms[] = new LanguageTerm($position, $type, $quality, $hasExplicitQval);
        }
        
        return $terms;
    }
    
    /**
     * @param array $availableTypes
     * @param array $parsedHeaderTerms
     * @return string Returns negotiated language or NULL if no acceptable values
     */
    private function doNegotiation(array $availableTypes, array $parsedHeaderTerms) {
        $scratchTerms = array();
        
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
            } elseif ($rangeMatches = $this->getRangeMatchesForType($type, $parsedHeaderTerms)) {
                foreach ($rangeMatches as $term) {
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
