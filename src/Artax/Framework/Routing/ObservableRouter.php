<?php
/**
 * Observable URI Router Class File
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
    Artax\Routing\Router,
    Artax\Routing\RouteStorage;

/**
 * Routes URIs against a pool of registered Route objects
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ObservableRouter extends Router {
    
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
        $this->notify('__sys.router.new');
    }
    
    /**
     * @param string $eventName
     * @return void
     */
    protected function notify($eventName) {
        $this->mediator->notify($eventName, $this);
    }
    
    /**
     * @param string $uriPath
     * @param RouteStorage $routes
     * @return bool
     * @throws MissingRoutesException
     */
    public function match($uriPath, RouteStorage $routes) {
        $matchResult = parent::match($uriPath, $routes);
        
        if ($matchResult) {
            $this->notify('__sys.router.matchFound');
        } else {
            $this->notify('__sys.router.noMatch');
        }
        
        return $matchResult;
    }
}
