<?php

namespace Artax\Negotiation;

use Exception,
    RuntimeException;

/**
 * Exception thrown if no available negotiables are acceptable
 */
class NotAcceptableException extends RuntimeException {
    
    /**
     * Requires a message explaining why no acceptable content can be served
     */
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
