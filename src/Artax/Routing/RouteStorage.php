<?php
/**
 * RouteStorage interface file
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the base package directory
 * @version     ${project.version}
 */

namespace Artax\Routing;

use Iterator,
    Countable,
    Serializable;

/**
 * An interface contract for route storage objects
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
interface RouteStorage extends Iterator, Countable, Serializable {

    /**
     * @param string $matchablePattern
     * @param string $resource
     * @return void
     */
    function addRoute($matchablePattern, $resource);
    
    /**
     * @param mixed $iterable An array, StdClass or Traversable key-value list of route parameters
     * @return void
     */
    function addAllRoutes($iterable);
}
