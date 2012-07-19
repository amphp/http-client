<?php
/**
 * HeaderTermParser Interface File
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Negotiation\Parsers;

/**
 * A design contract for parsers of negotiable HTTP accept headers
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
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
