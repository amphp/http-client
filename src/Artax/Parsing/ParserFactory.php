<?php

namespace Artax\Parsing;

class ParserFactory {
    
    private $hasPeclHttpExt;
    
    function __construct() {
        $this->hasPeclHttpExt = extension_loaded('http');
    }
    
    function make($mode = Parser::MODE_RESPONSE) {
        return $this->hasPeclHttpExt
            ? new PeclMessageParser($mode)
            : new MessageParser($mode);
    }
    
}

