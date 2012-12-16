<?php

namespace Artax;

use Ardent\Mediator,
    Artax\Http\Parsing\Tokenizer,
    Artax\Http\Parsing\ResponseParser;

class ResponseParserFactory {

    public function make($inputStream, Mediator $mediator = null) {
        $tokenizer = new Tokenizer($inputStream);
        $parser = new ResponseParser($tokenizer, $mediator);
        
        return $parser;
    }
}