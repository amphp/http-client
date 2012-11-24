<?php

namespace Artax\Http\Parsing\Symbols;

/**
 * SP = <US-ASCII SP, space (32)>
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
 */
class SP extends CHAR implements LWS {
    public function __construct() {}
    public function __toString() {
        return ' ';
    }
}