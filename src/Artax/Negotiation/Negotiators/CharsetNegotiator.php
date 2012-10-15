<?php
/**
 * CharsetNegotiator Class File
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Negotiation\Negotiators;

use Artax\Negotiation\NotAcceptableException;

/**
 * Negotiate a response character set from a raw Accept-Charset header
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class CharsetNegotiator extends BaseNegotiator {
    
    /**
     * Negotiates the appropriate charset from an `Accept-Charset` header
     * 
     * @param string $rawAcceptCharsetHeader
     * @param array $available
     * 
     * @return string
     * 
     * @throws UnexpectedValueException
     * @throws NotAcceptableException
     */
    public function negotiate($rawAcceptCharsetHeader, array $available) {
        // rfc2616-sec3.4: "HTTP character sets are identified by case-insensitive tokens."
        $available = array_map('strtolower', array_values($available));
        
        // rfc2616-sec14.2: "If no Accept-Charset header is present, the default is that 
        // any character set is acceptable."
        if (!$rawAcceptCharsetHeader) {
            return $available[0];
        }
        
        $terms = $this->parser->parse($rawAcceptCharsetHeader);
        list($accept, $reject) = $this->getAcceptablesFromParsedTerms($terms);
        
        $useOnWildcard = current(array_diff($available, $reject));
        
        foreach ($accept as $term) {
            if (in_array($term, $available)) {
                return $term->__toString();
            } elseif ($term == '*' && $useOnWildcard) {
                return $useOnWildcard;
            }
        }
        
        throw new NotAcceptableException(
            "No available charsets match `Accept-Charset: $rawAcceptCharsetHeader`. " .
            'Available set: [' . implode('|', $available) . ']'
        );
    }
}
