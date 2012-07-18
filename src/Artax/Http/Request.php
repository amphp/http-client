<?php
/**
 * Http Request Interface File
 * 
 * @category    Artax
 * @package     Http
 * @author      Levi Morrison <levim@php.net>
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the base package directory
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
     * @return string
     */
    function getUri();
    
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
