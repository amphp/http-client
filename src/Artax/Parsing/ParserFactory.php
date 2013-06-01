<?php

namespace Artax\Parsing;

class ParserFactory {
    
    private $hasPeclHttpExt;
    
    function __construct() {
        $this->hasPeclHttpExt = extension_loaded('http');
    }
    
    function make() {
        return $this->hasPeclHttpExt
            ? new PeclMessageParser(Parser::MODE_RESPONSE)
            : new MessageParser(Parser::MODE_RESPONSE);
    }
    
}

