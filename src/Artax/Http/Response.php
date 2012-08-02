<?php
/**
 * Http Response Class File
 * 
 * @category     Artax
 * @package      Http
 * @author       Levi Morrison <levim@php.net>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax\Http;

/**
 * A design contract for HTTP responses
 * 
 * @category     Artax
 * @package      Routing
 * @author       Levi Morrison <levim@php.net>
 */
interface Response extends Message {
    
    /**
     * @param string $rawStartLineStr
     */
    function setStartLine($rawStartLineStr);
    
    /**
     * @param string $httpVersion
     * @return string
     */
    function setHttpVersion($httpVersion);

    /**
     * @return int
     */
    function getStatusCode();

    /**
     * @param int $httpStatusCode
     * @return void
     */
    function setStatusCode($httpStatusCode);

    /**
     * @return string
     */
    function getStatusDescription();

    /**
     * @param string $httpStatusDescription
     * @return void
     */
    function setStatusDescription($httpStatusDescription);

    /**
     * @param string $header
     * @param string $value
     * @return void
     */
    function setHeader($header, $value);
    
    /**
     * @param string $rawHeaderStr
     */
    function setRawHeader($rawHeaderStr);

    /**
     * @param mixed $iterable
     * @return void
     */
    function setAllHeaders($iterable);
    
    /**
     * @param string $header
     * @return void
     */
    function removeHeader($header);
    
    /**
     * @param string $body
     * @return void
     */
    function setBody($body);

    /**
     * Formats and sends all headers prior to sending the message body.
     *
     * @return void
     */
    function send();

    /**
     * @return bool
     */
    function wasSent();
    
    /**
     * @param string $rawMessage
     */
    function populateFromRawMessage($rawMessage);
}
