<?php
/**
 * LanguageNegotiator Interface File
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Http\Negotiation\Negotiators;

use Artax\Http\Negotiation\NotAcceptableException;

/**
 * Negotiate a response language from a raw Accept-Language header
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class LanguageNegotiator extends BaseNegotiator {
    
    /**
     * Negotiates an appropriate language from an `Accept-Language` header
     * 
     * @param string $rawAcceptLanguageHeader
     * @param array $available
     * 
     * @return string
     * 
     * @throws UnexpectedValueException
     * @throws NotAcceptableException
     */
    public function negotiate($rawAcceptLanguageHeader, array $available) {
        // rfc2616-sec3.10: "... tags are case-insensitive."
        $available = array_map('strtolower', array_values($available));
        
        // rfc2616-sec14.4:
        // "If no Accept-Language header is present in the request, the server SHOULD 
        // assume that all languages are equally acceptable."
        if (!$rawAcceptLanguageHeader) {
            return $available[0];
        }
        
        $terms = $this->parser->parse($rawAcceptLanguageHeader);
        list($accept, $reject) = $this->getAcceptablesFromParsedTerms($terms);
        
        $useOnwildcard = $this->selectWildcardLang($available, $reject);
        $availableRanges = $this->buildAvailableRanges($available);
        
        foreach ($accept as $term) {
            if (in_array($term, $available)) {
                return $term->__toString();
            } elseif ($term == '*' && $useOnwildcard) {
                return $useOnwildcard;
            } elseif (isset($availableRanges[$term->__toString()])
                && $acceptInRange = array_diff($availableRanges[$term->__toString()], $reject)
            ) {
                return current($acceptInRange);
            }
        }
        
        throw new NotAcceptableException(
            "No available languages match `Accept-Language: $rawAcceptLanguageHeader`. " .
            'Available set: [' . implode('|', $available) . ']'
        );
    }
    
    /**
     * @param array $available
     * @return array
     */
    private function buildAvailableRanges(array $available) {
        $ranges = array();
        
        foreach ($available as $type) {
            $dashPosition = strpos($type, '-');
            $key = (false !== $dashPosition) ? substr($type, 0, $dashPosition) : $type;
            $ranges[$key][] = $type;
        }
        
        return $ranges;
    }
    
    /**
     * @param array $available
     * @param array $reject
     * @return string
     */
    private function selectWildcardLang(array $available, array $reject) {
        $explicitRemovalArr = array_diff($available, $reject);
        foreach ($explicitRemovalArr as $lang) {
            $dashPosition = strpos($lang, '-');
            $key = null === $dashPosition ? $lang : substr($lang, 0, $dashPosition);
            if (!in_array($key, $reject)) {
                return $lang;
            }
        }
        return null;
    }
}
