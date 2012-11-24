<?php

namespace Artax\Http\Parsing\Symbols;

/**
 * A special token carrying a block of data
 * 
 * This symbol is not from the HTTP specification and is used specifically by the MessageTokenizer
 * to indicate to token consumers that the token is of variable size and value.
 */
class BLOCK extends Symbol {
    private $byteCount;
    public function __construct($value) {
        parent::__construct($value);
        $this->byteCount = strlen($value);
    }
    public function getSize() {
        return $this->byteCount;
    }
}