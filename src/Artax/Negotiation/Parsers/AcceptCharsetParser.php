<?php
/**
 * AcceptCharset HTTP Header Parser Class File
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the base package directory
 * @version     ${project.version}
 */
namespace Artax\Negotiation\Parsers;

/**
 * Parses charsets from the raw Accept-Charset header, ordering by client-preference
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class AcceptCharsetParser extends BaseParser {
    
    /**
     * Parses a raw Accept-Charset header into an array ordered by client preference
     * 
     * @param string $rawAcceptCharsetHeader
     * 
     * @return array
     */
    public function parse($rawAcceptCharsetHeader) {
        $terms = $this->getTermsFromRawHeader($rawAcceptCharsetHeader);
        
        // As per rfc2616-sec14.2:
        //
        // The special value "*", if present in the Accept-Charset field, matches every 
        // character set (including ISO-8859-1) which is not mentioned elsewhere in the 
        // Accept-Charset field. If no "*" is present in an Accept-Charset field, then all
        // character sets not explicitly mentioned get a quality value of 0, except for 
        // ISO-8859-1, which gets a quality value of 1 if not explicitly mentioned.
        $coalescedTerms = $this->coalesceWildcardAndIso88591($terms);
        
        usort($coalescedTerms, array($this, 'sortByQuality'));
        
        return $coalescedTerms;
    }
    
    /**
     * @param array $terms
     * @return array
     */
    private function coalesceWildcardAndIso88591(array $terms) {
        if (in_array('iso-8859-1', $terms) || in_array('*', $terms)) {
            return $terms;
        }
        $terms[] = $this->acceptTermFactory->make(count($terms), 'iso-8859-1', 1, false);
        
        return $terms;
    }
}
