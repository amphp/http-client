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
 * This interface is modelled after RFC 2616, section 5. Source:
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
    public function getScheme();
    
    /**
     * @return string
     */
    public function getHost();
    
    /**
     * @return string
     */
    public function getPort();
    
    /**
     * @return string
     */
    public function getPath();
    
    /**
     * @return string
     */
    public function getQuery();
    
    /**
     * @return string
     */
    public function getFragment();
    
    /**
     * Returns Authority with protected user info obscured by asterisks as per rfc3986-3.2.1
     * @return string
     */
    public function getAuthority();
    
    /**
     * Returns Authority without protected user info obscured by asterisks
     * @return string
     */
    public function getRawAuthority();
    
    /**
     * Returns user info with the password obscured by asterisks as per rfc3986-3.2.1
     * @return string
     */
    public function getUserInfo();
    
    /**
     * Returns user info without protected user info obscured by asterisks
     * @return string
     */
    public function getRawUserInfo();
    
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
     * @return bool
     */
    function hasQueryParameter($parameterName);
}
