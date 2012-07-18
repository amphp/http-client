<?php

use Artax\Routing\RoutePool,
    Artax\Routing\StdRouteFactory,
    Artax\Framework\Routing\ObservableRouter;

class ObservableRouterTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Routing\ObservableRouter::__construct
     * @covers Artax\Framework\Routing\ObservableRouter::notify
     */
    public function testConstructorNotifiesListeners() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('__sys.router.new');
                 
        $router = new ObservableRouter($mediator);
    }
    
    /**
     * @covers Artax\Framework\Routing\ObservableRouter::setRoutes
     * @covers Artax\Framework\Routing\ObservableRouter::notify
     */
    public function testSetRoutesNotifiesListeners() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        
        $router = new ObservableRouter($mediator);
        
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('__sys.router.setRoutes');
        
        $routes = $this->getMock('Artax\\Routing\\RouteStorage');
        $router->setRoutes($routes);
    }
    
    /**
     * @covers Artax\Framework\Routing\ObservableRouter::match
     * @covers Artax\Framework\Routing\ObservableRouter::notify
     */
    public function testMatchNotifiesListenersIfMatchFoundAndReturnsTrue() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $router = new ObservableRouter($mediator);
        
        $routes = new RoutePool(new StdRouteFactory);
        $routes->addRoute('/widgets', 'WidgetResource');
               
        $router->setRoutes($routes);
        
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('__sys.router.matchFound');
        
        $this->assertTrue($router->match('/widgets'));
    }
    
    /**
     * @covers Artax\Framework\Routing\ObservableRouter::match
     * @covers Artax\Framework\Routing\ObservableRouter::notify
     */
    public function testMatchNotifiesListenersOnMatchFailureAndReturnsFalse() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $router = new ObservableRouter($mediator);
        
        $routes = new RoutePool(new StdRouteFactory);
        $routes->addRoute('/kumqats', 'KumqatResource');
               
        $router->setRoutes($routes);
        
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('__sys.router.noMatch');
        
        $this->assertFalse($router->match('/widgets'));
    }
}
