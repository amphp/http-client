<?php

namespace Artax\Http;

/**
 * Interface modeled after RFC 2616 Section 5
 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html
 */
interface Request extends Message {

    /**
     * Access the HTTP method verb
     */
    function getMethod();

    /**
     * Does the request's HTTP method verb support an entity body?
     */
    function allowsEntityBody();

    /**
     * Retrive a specific URI query parameter by name
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

    /**
     * Get the HTTP message request line
     */
    function getRequestLine();

    /**
     * Get the HTTP message request line with a proxy-style absolute URI
     */
    function getProxyRequestLine();

    /**
     * Access the request URI with protected user info obscured by asterisks as per rfc3986-3.2.1
     */
    function getUri();

    /**
     * Access the request URI scheme
     */
    function getScheme();

    /**
     * Access the request URI host
     */
    function getHost();

    /**
     * Access the request URI port
     */
    function getPort();

    /**
     * Access the request URI path
     */
    function getPath();

    /**
     * Access the request URI query string
     */
    function getQuery();

    /**
     * Access the request URI fragment
     */
    function getFragment();

    /**
     * Access URI authority with protected user info obscured by asterisks as per rfc3986-3.2.1
     */
    function getAuthority();

    /**
     * Access URI user info with the password obscured by asterisks as per rfc3986-3.2.1
     */
    function getUserInfo();
}