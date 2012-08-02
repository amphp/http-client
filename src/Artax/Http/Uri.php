<?php
/**
 * Uri Interface File
 * 
 * @category    Artax
 * @package     Http
 * @author      Levi Morrison <levim@php.net>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */

namespace Artax\Http;

/**
 * A design contract for modeling HTTP URIs
 * 
 * This class is modelled after RFC 2396 and RFC 3986:
 * http://www.ietf.org/rfc/rfc2396.txt
 * http://www.ietf.org/rfc/rfc3986.txt
 *
 * @category    Artax
 * @package     Http
 * @author      Levi Morrison <levim@php.net>
 */
interface Uri {
    
    /**
     * Returns the URI scheme component (http|https)
     */
    function getScheme();
    
    /**
     * Returns the User Info URI component, masking protected user info as per rfc3986-3.2.1
     */
    function getUserInfo();
    
    /**
     * Returns the User Info URI component without masking protected user info
     */
    function getRawUserInfo();
    
    /**
     * Returns the URI host component
     */
    function getHost();
    
    /**
     * Returns the URI port component
     */
    function getPort();
    
    /**
     * Returns the URI's authority, masking protected user info as per rfc3986-3.2.1
     */
    function getAuthority();
    
    /**
     * Returns the URI's raw authority without masking protected user info
     */
    function getRawAuthority();
    
    /**
     * Returns the URI path component
     */
    function getPath();
    
    /**
     * Returns the URI query component
     */
    function getQuery();
    
    /**
     * Returns the URI fragment component
     */
    function getFragment();
    
    /**
     * Returns the URI string without masking protected user info data
     */
    function getRawUri();
    
    /**
     * Returns the URI string, masking protected user info data according to rfc3986-3.2.1
     */
    function __toString();
}
