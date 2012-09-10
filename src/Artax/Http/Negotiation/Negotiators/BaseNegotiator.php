<?php
/**
 * BaseNegotiator Class File
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Http\Negotiation\Negotiators;

use StdClass,
    InvalidArgumentException,
    Artax\Http\Negotiation\NegotiableTerm,
    Artax\Http\Negotiation\Parsers\HeaderParser;

/**
 * An abstract HeaderNegotiator implementation
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
abstract class BaseNegotiator implements HeaderNegotiator {
    
    /**
     * @var NegotiableHeaderParser
     */
    protected $parser;
    
    /**
     * @param NegotiableHeaderParser $parser
     * @return void
     */
    public function __construct(HeaderParser $parser) {
        $this->parser = $parser;
    }
    
    /**
     * @param array $terms
     * @return void
     */
    protected function getAcceptablesFromParsedTerms(array $terms) {
        $reject = array_filter($terms, function(NegotiableTerm $term) {
            return $term->getQuality() == 0;
        });
        $accept = array_diff($terms, $reject);
        
        return array($accept, $reject);
    }
}
