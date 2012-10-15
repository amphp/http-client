<?php
/**
 * BaseParser Class File
 *
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Negotiation\Parsers;

use StdClass,
    Artax\Negotiation\NegotiableTerm,
    Artax\Negotiation\AcceptTerm;

/**
 * Parses a raw Accept header into an array of AcceptTerm ordered by client preference
 *
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
abstract class BaseParser implements HeaderParser {

    /**
     * Parses a raw Accept header into an array of AcceptTerms ordered by client preference
     *
     * @param string $rawHeader
     * @return array
     */
    public function parse($rawHeader) {
        $terms = $this->getTermsFromRawHeader($rawHeader);
        usort($terms, array($this, 'sortByQuality'));
        return $terms;
    }

    /**
     * @param string $rawHeader
     * @return array
     */
    protected function getTermsFromRawHeader($rawHeader) {
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
     * Sorts an array of AcceptTerm objects by quality-factor
     *
     * @param NegotiableTerm $a
     * @param NegotiableTerm $b
     * @return int
     */
    protected function sortByQuality(NegotiableTerm $a, NegotiableTerm $b) {
        $diff = $b->getQuality() - $a->getQuality();
        if (!$diff) {
            return $this->sortQualityTie($a, $b);
        }
        return $diff/abs($diff);
    }

    /**
     * Sorts quality-factor ties by specificity as per rfc2616-sec14.1
     *
     * If both terms explicitly specify the same quality factor, the first term specified wins.
     *
     * @param NegotiableTerm $a
     * @param NegotiableTerm $b
     * @return int
     */
    protected function sortQualityTie(NegotiableTerm $a, NegotiableTerm $b) {
        if ($explicitDiff = ($b->hasExplicitQuality() - $a->hasExplicitQuality())) {
            return $explicitDiff/abs($explicitDiff);
        }
        return $a->getPosition() - $b->getPosition();
    }
}
