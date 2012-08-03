<?php

namespace Artax\Http;

interface MutableResponse extends MutableMessage, Response {
    
    /**
     * @param string $rawStartLineStr
     */
    function setStartLine($rawStartLineStr);

    /**
     * @param int $httpStatusCode
     */
    function setStatusCode($httpStatusCode);

    /**
     * @param string $httpStatusDescription
     */
    function setStatusDescription($httpStatusDescription);
    
    /**
     * @param Response $response
     */
    function populateFromResponse(Response $response);
    
}
