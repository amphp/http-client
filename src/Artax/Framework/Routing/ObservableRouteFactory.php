<?php
/**
 * ObservableRouteFactory Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Routing;

use Artax\Events\Mediator,
    Artax\Routing\RouteFactory;

/**
 * Creates observable routes
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ObservableRouteFactory implements RouteFactory {
    
    /**
     * @var Mediator
     */
    private $mediator;
    
    /**
     * @param Mediator $mediator
     * @return void
     */
    public function __construct(Mediator $mediator) {
        $this->mediator = $mediator;
    }
    
    /**
     * @param string $uriPattern
     * @param string $resourceClass
     * @return ObservableRoute
     */
    public function make($uriPattern, $resourceClass) {
        return new ObservableRoute($this->mediator, $uriPattern, $resourceClass);
    }
}
