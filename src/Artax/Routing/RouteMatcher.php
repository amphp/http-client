<?php
/**
 * RouteMatcher Interface File
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Routing;

/**
 * An interface for matching patterns against a pool of resource routes
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
interface RouteMatcher {
    
    /**
     * @param string $matchablePattern
     * @return bool
     */
    function match($matchablePattern, RouteStorage $routeStorage);
    
    /**
     * @return string
     */
    function getMatchedResource();
    
    /**
     * @return array
     */
    function getMatchedArgs();
}
