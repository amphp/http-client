<?php

namespace Artax\Negotiation;

use Spl\ValueException,
    Artax\Negotiation\Terms\Term;

abstract class AbstractNegotiator implements Negotiator {
    
    /**
     * @param array $availables
     * @throws \Spl\ValueException On invalid quality value
     * @return void
     */
    protected function validateQualityValues(array $availables) {
        foreach ($availables as $type => $qval) {
            if (!is_numeric($qval) || $qval > 1 || $qval <= 0) {
                throw new ValueException(
                    'Available type arrays require numeric values between (0-1] (exclusive ' .
                    "of 0, inclusive of 1); `$qval` specified"
                );
            }
            if (strstr($type, '*')) {
                throw new ValueException(
                    "Available types may not contain wildcards: `$type`"
                );
            }
        }
    }
    
    /**
     * @param string $rawHeader
     * @return array
     */
    protected function parseTermsFromHeader($rawHeader) {
        $terms = array();

        foreach (preg_split('/\s*,\s*/', $rawHeader) as $pos => $term) {
            if (preg_match("{^(\S+)\s*;\s*(?:q|level)=([0-9\.]+)}i", $term, $match)) {
                $type = $match[1];
                $quality = $match[2];
                $explicitQuality = true;
            } else {
                $type = $term;
                $quality = 1;
                $explicitQuality = false;
            }

            $terms[] = new Term($pos, $type, $quality, $explicitQuality);
        }

        return $terms;
    }
    
    /**
     * @param array $scratchTerms
     * @return array
     */
    protected function sortTermsByPreference(array $scratchTerms) {
        uasort($scratchTerms, array($this, 'sortTerms'));
        return array_values($scratchTerms);
    }
    
    /**
     * Sorts terms by q-value.
     * 
     * In the event of a tie, terms with an explicitly defined q-value take precedence over terms 
     * with implied quality values. If there is still no difference after comparing the explicit
     * qualities, the first term specified in the raw header wins.
     * 
     * @param Term $a
     * @param Term $b
     * @return int
     */
    protected function sortTerms(Term $a, Term $b) {
        $aqval = $a->getQuality();
        $bqval = $b->getQuality();
        
        if ($aqval == $bqval) {
            return ($b->hasExplicitQuality() - $a->hasExplicitQuality()) ?: ($a->getPosition() - $b->getPosition());
        }
        
        return ($aqval > $bqval) ? -1 : 1;
    }
    
    /**
     * @param array $scratchTerms
     * @return array
     */
    protected function filterRejectedTerms(array $scratchTerms) {
        // Build a list of explicitly rejected types
        $shouldReject = array();
        foreach ($scratchTerms as $st) {
            if (!$st->getQuality()) {
                $shouldReject[] = $st->getType();
            }
        }
        
        // Filter out any explicitly rejected types
        $scratchTerms = array_filter($scratchTerms, function($st) use ($shouldReject) {
            return !in_array($st->getType(), $shouldReject);
        });
        
        return array_values($scratchTerms);
    }
}