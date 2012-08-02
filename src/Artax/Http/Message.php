<?php
/**
 * HTTP Message Interface File
 * 
 * @category    Artax
 * @package     Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Http;

/**
 * This interface is modeled after RFC 2616, section 4:
 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
interface Message {
    
    /**
     * @return string Returns the HTTP version number, not prefixed by `HTTP/`
     */
    function getHttpVersion();

    /**
     * @param string $headerName
     * @return string
     */
    function getHeader($headerName);

    /**
     * @return array
     */
    function getAllHeaders();

    /**
     * @param string $headerName
     * @return bool
     */
    function hasHeader($headerName);
    
    /**
     * @return string
     */
    function getBody();
    
    /**
     * @return string
     */
    function __toString();
}
