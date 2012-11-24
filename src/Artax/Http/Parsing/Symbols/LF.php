<?php

namespace Artax\Http\Parsing\Symbols;

/**
 * LF = <US-ASCII LF, linefeed (10)>
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
 */
class LF extends CTL implements EOL {
    public function __construct() {}
    public function __toString() {
        return "\n";
    }
}