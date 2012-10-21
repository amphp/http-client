<?php

namespace Artax\Negotiation;

use Spl\ValueException;

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
                    'Negotiable value arrays require numeric values between (0-1] (exclusive ' .
                    "of 0, inclusive of 1); `$qval` specified"
                );
            }
            if (strstr($type, '*')) {
                throw new ValueException(
                    "Negotiable values may not contain wildcards: `$type`"
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

            $terms[] = new AcceptTerm($pos, $type, $quality, $explicitQuality);
        }

        return $terms;
    }
    
    /**
     * @param array $scratchTerms
     * @return array
     */
    protected function sortScratchTermsByPreference(array $scratchTerms) {
        uasort($scratchTerms, array($this, 'sortScratchTerms'));
        return array_values($scratchTerms);
    }
    
    /**
     * Sorts terms by q-value.
     * 
     * In the event of a tie, terms with an explicitly defined q-value take precedence over terms 
     * with implied quality values. If there is still no difference after comparing the explicit
     * qualities, the first term specified in the raw header wins.
     * 
     * @param ScratchTerm $a
     * @param ScratchTerm $b
     * @return int
     */
    protected function sortScratchTerms(ScratchTerm $a, ScratchTerm $b) {
        $aqval = $a->getQval();
        $bqval = $b->getQval();
        
        if ($aqval == $bqval) {
            return ($b->isExplicit() - $a->isExplicit()) ?: ($a->getPosition() - $b->getPosition());
        }
        
        return ($aqval > $bqval) ? -1 : 1;
    }
    
    /**
     * @param array $scratchTerms
     * @return array
     */
    protected function filterRejectedScratchTerms(array $scratchTerms) {
        // Build a list of explicitly rejected types
        $shouldReject = array();
        foreach ($scratchTerms as $st) {
            if (!$st->getQval()) {
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