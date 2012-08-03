<?php

namespace Artax\Http;

interface MutableRequest extends MutableMessage, Request {
    
    /**
     * @param string $method
     */
    function setMethod($method);

    /**
     * @param string $uri
     */
    function setUri($uri);
    
    /**
     * @param Request $request
     */
    function populateFromRequest(Request $request);
}
