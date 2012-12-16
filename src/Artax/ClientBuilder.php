<?php

namespace Artax;

use Ardent\Mediator,
    Ardent\HashingMediator,
    Artax\Client,
    Artax\RequestWriterFactory,
    Artax\ResponseParserFactory;

class ClientBuilder {

    public function build(Mediator $mediator = null) {
        $writerFactory = new RequestWriterFactory;
        $parserFactory = new ResponseParserFactory;
        $mediator = $mediator ?: new HashingMediator;
        
        return new Client($writerFactory, $parserFactory, $mediator);
    }
}