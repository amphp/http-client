<?php

namespace Artax\Negotiation\Parsers;

/**
 * A design contract for parsers of negotiable HTTP accept headers
 */
interface HeaderParser {
    
    /**
     * Parse an array of acceptable/rejected terms from a raw HTTP Accept header
     * 
     * @param string $rawHttpHeader
     * @return array
     */
    function parse($rawHttpHeader);
}
