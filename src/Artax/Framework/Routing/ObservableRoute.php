<?php
/**
 * ObservableRoute Class File
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Routing;

use Artax\Events\Mediator,
    Artax\Routing\StdRoute;

/**
 * Models HTTP URI-resource routes and broadcasts observable events to listeners
 * 
 * @category    Artax
 * @package     Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ObservableRoute extends StdRoute {
    
    /**
     * @var Mediator
     */
    private $mediator;
    
    /**
     * @param Mediator $mediator
     * @param string $uriPattern
     * @param string $resourceClass
     * @return void
     */
    public function __construct(Mediator $mediator, $uriPattern, $resourceClass) {
        $this->mediator = $mediator;
        parent::__construct($uriPattern, $resourceClass);
        $this->notify('__sys.route.new');
    }
    
    /**
     * @param string $eventName
     * @return void
     */
    private function notify($eventName) {
        $this->mediator->notify($eventName, $this);
    }
    
    /**
     * @param string $uriPattern
     * @return void
     */
    public function setPattern($uriPattern) {
        parent::setPattern($uriPattern);
        $this->notify('__sys.route.setPattern');
    }
    
    /**
     * @param string $resourceClass
     * @return void
     */
    public function setResource($resourceClass) {
        parent::setResource($resourceClass);
        $this->notify('__sys.route.setResource');
    }
}
