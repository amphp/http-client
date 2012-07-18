<?php
/**
 * ObservableRoutePool Class File
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
    Artax\Routing\RoutePool,
    Artax\Routing\RouteFactory;

/**
 * An observable, iterable, serializable pool of resource routes used for URI matching
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ObservableRoutePool extends RoutePool {

    /**
     * @var Mediator
     */
    private $mediator;
    
    /**
     * @param Mediator $mediator
     * @param RouteFactory $routeFactory
     * @return void
     */
    public function __construct(Mediator $mediator, RouteFactory $routeFactory) {
        $this->mediator = $mediator;
        parent::__construct($routeFactory);
        $this->notify('__sys.routePool.new');
    }
    
    /**
     * @param string $eventName
     * @return void
     */
    protected function notify($eventName) {
        $this->mediator->notify($eventName, $this);
    }
    
    /**
     * @return void
     */
    public function __destruct() {
        $this->notify('__sys.routePool.die');
    }
    
    /**
     * @param string $uriPattern
     * @param string $resourceClass
     * @return void
     */
    public function addRoute($uriPattern, $resourceClass) {
        parent::addRoute($uriPattern, $resourceClass);
        $this->notify('__sys.routePool.addRoute');
    }
}
