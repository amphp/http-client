<?php
/**
 * RouteFactory Class File
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
 * A factory for creating new standard routes
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class StdRouteFactory implements RouteFactory {
    
    /**
     * @param string $matchablePattern
     * @param mixed $resource
     * @return StdRoute
     */
    public function make($matchablePattern, $resource) {
        return new StdRoute($matchablePattern, $resource);
    }
}
