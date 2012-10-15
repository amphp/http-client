<?php
/**
 * EncodingNegotiator Class File
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
 * Negotiate a response encoding from a raw Accept-Encoding header
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class EncodingNegotiator extends BaseNegotiator {
    
    /**
     * Negotiates the appropriate content coding from an `Accept-Encoding` header
     * 
     * @param string $rawAcceptEncodingHeader
     * @param array $available
     * 
     * @return string
     * 
     * @throws UnexpectedValueException
     * @throws NotAcceptableException
     */
    public function negotiate($rawAcceptEncodingHeader, array $available) {
        // rfc2616-sec3.5: "All content-coding values are case-insensitive."
        $available = array_map('strtolower', array_values($available));
        
        // rfc2616-sec14.3:
        // "If no Accept-Encoding field is present in a request, the server MAY assume that
        // the client will accept any content coding. In this case, if "identity" is one of
        // the available content-codings, then the server SHOULD use the "identity" content-coding,
        // unless it has additional information that a different content-coding is meaningful
        // to the client."
        if (!$rawAcceptEncodingHeader) {
            return in_array('identity', $available) ? 'identity' : $available[0];
        }
        
        $terms = $this->parser->parse($rawAcceptEncodingHeader);
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
            "No available message encodings match `Accept-Encoding: $rawAcceptEncodingHeader`" .
            '. Available set: [' . implode('|', $available) . ']'
        );
    }
}
