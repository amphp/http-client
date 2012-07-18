<?php
/**
 * Route Interface File
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the base package directory
 * @version     ${project.version}
 */
namespace Artax\Routing;

use Serializable;

/**
 * A contract for resource routes
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
interface Route extends Serializable {
    
    /**
     * @return string
     */
    function getPattern();
    
    /**
     * @param string $matchablePattern
     * @return void
     */
    function setPattern($matchablePattern);
    
    /**
     * @return string
     */
    function getResource();
    
    /**
     * @param mixed $resourceClass
     * @return void
     */
    function setResource($resource);
}
