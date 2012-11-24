<?php

namespace Artax\Http\Parsing\Symbols;

abstract class Symbol {
    private $value = '';
    public function __construct($value) {
        $this->value = $value;
    }
    public function __toString() {
        return $this->value;
    }
    public function getSize() {
        return 1;
    }
}