<?php

namespace Artax\Http;

/**
 * Interface modeled after RFC 2616 Section 5
 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html
 */
interface Request extends Message {
    
    /**
     * Should return an all-caps HTTP method verb
     */
    function getMethod();

    /**
     * Should return a URI with protected user info obscured by asterisks as per rfc3986-3.2.1
     */
    function getUri();

    /**
     * Should return a URI without protected user info obscured by asterisks
     */
    function getRawUri();
    
    function getScheme();
    
    function getHost();
    
    function getPort();
    
    function getPath();
    
    function getQuery();
    
    function getFragment();
    
    /**
     * Should return Authority with protected user info obscured by asterisks as per rfc3986-3.2.1
     */
    function getAuthority();
    
    /**
     * Should return Authority without protected user info obscured by asterisks
     */
    function getRawAuthority();
    
    /**
     * Should return user info with the password obscured by asterisks as per rfc3986-3.2.1
     */
    function getUserInfo();
    
    /**
     * Should return user info without protected user info obscured by asterisks
     */
    function getRawUserInfo();
    
    /**
     * @param string $parameterName
     */
    function getQueryParameter($parameterName);
    
    /**
     * @param string $parameterName
     */
    function hasQueryParameter($parameterName);
    
    function getAllQueryParameters();
}
