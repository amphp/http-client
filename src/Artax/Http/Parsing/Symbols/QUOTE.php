<?php

namespace Artax\Http\Parsing\Symbols;

/**
 * <"> = <US-ASCII double-quote mark (34)>
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
 */
class QUOTE extends CHAR {
    public function __construct() {}
    public function __toString() {
        return '"';
    }
}