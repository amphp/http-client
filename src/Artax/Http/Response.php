<?php

namespace Artax\Http;

interface Response extends Message {
    
    /**
     * @param string $rawStartLineStr
     */
    function setStartLine($rawStartLineStr);
    
    /**
     * @param string $httpVersion
     */
    function setHttpVersion($httpVersion);

    function getStatusCode();

    /**
     * @param int $httpStatusCode
     */
    function setStatusCode($httpStatusCode);

    function getStatusDescription();

    /**
     * @param string $httpStatusDescription
     */
    function setStatusDescription($httpStatusDescription);

    /**
     * @param string $header
     * @param string $value
     */
    function setHeader($header, $value);
    
    /**
     * @param string $rawHeaderStr
     */
    function setRawHeader($rawHeaderStr);

    /**
     * @param mixed $iterable
     */
    function setAllHeaders($iterable);
    
    /**
     * @param string $header
     */
    function removeHeader($header);
    
    /**
     * @param string $body
     */
    function setBody($body);

    /**
     * Formats and sends all headers prior to sending the message body.
     */
    function send();

    function wasSent();
    
    /**
     * @param string $rawMessage
     */
    function populateFromRawMessage($rawMessage);
}
