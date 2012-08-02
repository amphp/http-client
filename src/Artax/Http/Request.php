<?php
/**
 * Http Request Interface File
 * 
 * @category    Artax
 * @package     Http
 * @author      Levi Morrison <levim@php.net>
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Http;

/**
 * This interface is modelled after RFC 2616, section 5.
 * 
 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html
 * 
 * @category    Artax
 * @package     Http
 * @author      Levi Morrison <levim@php.net>
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
interface Request extends Message {
    
    /**
     * @return string The HTTP method, upper-cased.
     */
    function getMethod();

    /**
     * Returns a URI with protected user info obscured by asterisks as per rfc3986-3.2.1
     * @return string
     */
    function getUri();

    /**
     * Returns a URI without protected user info obscured by asterisks
     * @return string
     */
    function getRawUri();
    
    /**
     * @return string
     */
    function getScheme();
    
    /**
     * @return string
     */
    function getHost();
    
    /**
     * @return string
     */
    function getPort();
    
    /**
     * @return string
     */
    function getPath();
    
    /**
     * @return string
     */
    function getQuery();
    
    /**
     * @return string
     */
    function getFragment();
    
    /**
     * Returns Authority with protected user info obscured by asterisks as per rfc3986-3.2.1
     * @return string
     */
    function getAuthority();
    
    /**
     * Returns Authority without protected user info obscured by asterisks
     * @return string
     */
    function getRawAuthority();
    
    /**
     * Returns user info with the password obscured by asterisks as per rfc3986-3.2.1
     * @return string
     */
    function getUserInfo();
    
    /**
     * Returns user info without protected user info obscured by asterisks
     * @return string
     */
    function getRawUserInfo();
    
    /**
     * @param string $parameterName
     * @return array
     */
    function getQueryParameter($parameterName);
    
    /**
     * @return array
     */
    function getAllQueryParameters();
    
    /**
     * @param string $parameterName
     */
    function hasQueryParameter($parameterName);
}
