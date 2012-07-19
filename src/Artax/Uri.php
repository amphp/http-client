<?php
/**
 * Uri Class File
 * 
 * @category     Artax
 * @author       Levi Morrison <levim@php.net>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */

namespace Artax;

/**
 * An interface contract for modeling URLs
 * 
 * This class is modelled after RFC 2396: http://www.ietf.org/rfc/rfc2396.txt
 *
 * @category     Artax
 * @package      Routing
 * @author       Levi Morrison <levim@php.net>
 */
interface Uri {

    /**
     * @return string
     */
    function getScheme();
    
    /**
     * @return string
     */
    function getUserInfo();
    
    /**
     * @return string
     */
    function getHost();

    /**
     * @return int
     */
    function getPort();
    
    /**
     * @return string
     */
    function getAuthority();
    
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
     * @abstract
     * @return string
     */
    function __toString();

}
