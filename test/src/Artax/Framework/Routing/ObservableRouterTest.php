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
     * @covers Artax\Framework\Routing\ObservableRouter::match
     * @covers Artax\Framework\Routing\ObservableRouter::notify
     */
    public function testMatchNotifiesListenersIfMatchFoundAndReturnsTrue() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $router = new ObservableRouter($mediator);
        
        $routes = new RoutePool(new StdRouteFactory);
        $routes->addRoute('/widgets', 'WidgetResource');
               
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('__sys.router.matchFound');
        
        $this->assertTrue($router->match('/widgets', $routes));
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
               
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('__sys.router.noMatch');
        
        $this->assertFalse($router->match('/widgets', $routes));
    }
}
