<?php
/**
 * NotAcceptableException Class File
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Http\Negotiation;

use Exception,
    RuntimeException;

/**
 * Exception thrown if no available negotiables are acceptable
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class NotAcceptableException extends RuntimeException {
    
    /**
     * Requires a message explaining why no acceptable content can be served
     */
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
