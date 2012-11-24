<?php

namespace Artax\Http\Parsing\Symbols;

/**
 * CR = <US-ASCII CR, carriage return (13)>
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
 */
class CR extends CTL implements EOL {
    public function __construct() {}
    public function __toString() {
        return "\r";
    }
}