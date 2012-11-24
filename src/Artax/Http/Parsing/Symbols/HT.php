<?php

namespace Artax\Http\Parsing\Symbols;

/**
 * HT = <US-ASCII HT, horizontal-tab (9)>
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
 */
class HT extends CTL implements LWS {
    public function __construct() {}
    public function __toString() {
        return "\t";
    }
}