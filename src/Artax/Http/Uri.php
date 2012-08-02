<?php

namespace Artax\Http;

/**
 * Interface modeled after RFC 2396 & RFC 3986
 * http://www.ietf.org/rfc/rfc2396.txt
 * http://www.ietf.org/rfc/rfc3986.txt
 */
interface Uri {
    
    function getScheme();
    
    function getHost();
    
    function getPort();
    
    function getPath();
    
    function getQuery();
    
    function getFragment();
    
    /**
     * Should return URI user info, masking protected user info data according to rfc3986-3.2.1
     */
    function getUserInfo();
    
    /**
     * Should return the URI Authority, masking protected user info data according to rfc3986-3.2.1
     */
    function getAuthority();
    
    /**
     * Should return the URI string, masking protected user info data according to rfc3986-3.2.1
     */
    function __toString();
    
    /**
     * Should return the URI string without masking protected user info data
     */
    function getRawUri();
    
    /**
     * Should return the URI Authority without masking protected user info data
     */
    function getRawAuthority();
    
    /**
     * Should return the URI user info without masking protected user info data
     */
    function getRawUserInfo();
}
