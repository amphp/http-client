<?php
/**
 * RouteFactory Interface File
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Routing;

use Artax\Events\Mediator;

/**
 * A design contract for route factories
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
interface RouteFactory {
    
    /**
     * @param string $matchablePattern
     * @param mixed $resource
     * @return Route
     */
    public function make($matchablePattern, $resource);
}
