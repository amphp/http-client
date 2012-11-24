<?php

namespace Artax\Http;

/**
 * Adds Request-specific accessor methods to the base Message interface
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html
 * @codeCoverageIgnore
 */
interface Request extends Message {
    
    const GET = 'GET';
    const POST = 'POST';
    const PUT = 'PUT';
    const HEAD = 'HEAD';
    const DELETE = 'DELETE';
    const OPTIONS = 'OPTIONS';
    const TRACE = 'TRACE';
    const CONNECT = 'CONNECT';
    
    /**
     * Access the HTTP method verb
     */
    function getMethod();

    /**
     * Access the request URI string
     */
    function getUri();
    function getScheme();
    function getUser();
    function getPass();
    function getHost();
    function getPort();
    function getPath();
    function getQuery();
    function getFragment();
    function getAuthority();

    /**
     * Retrieve a specific URI query parameter by name
     *
     * @param string $parameterName
     */
    function getQueryParameter($parameterName);

    /**
     * Does the request contain the specified query parameter?
     *
     * @param string $parameterName
     */
    function hasQueryParameter($parameterName);

    /**
     * Access an associative array of query string parameters
     */
    function getAllQueryParameters();
}