<?php

use Artax\Injection\Provider,
    Artax\Events\Notifier,
    Artax\Injection\ReflectionPool,
    Artax\Routing\StdRouteFactory,
    Artax\Framework\Routing\ObservableRoutePool;

class ObservableRoutePoolTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Routing\ObservableRoutePool::__construct
     * @covers Artax\Framework\Routing\ObservableRoutePool::notify
     */
    public function testConstructorCallsParentAndNotifiesListeners() {
        $reflCacher = new ReflectionPool;
        $injector   = new Provider($reflCacher);
        $mediator   = new Notifier($injector);
        
        $routeFactory = $this->getMock('Artax\\Routing\\RouteFactory');
        $routePool = new ObservableRoutePool($mediator, $routeFactory);
        
        $this->assertEquals(1, $mediator->countNotifications('__sys.routePool.new'));
    }
    
    /**
     * @covers Artax\Framework\Routing\ObservableRoutePool::__destruct
     * @covers Artax\Framework\Routing\ObservableRoutePool::notify
     */
    public function testDestructorNotifiesListeners() {
        $reflCacher = new ReflectionPool;
        $injector   = new Provider($reflCacher);
        $mediator   = new Notifier($injector);
        
        $routeFactory = $this->getMock('Artax\\Routing\\RouteFactory');
        $routePool = new ObservableRoutePool($mediator, $routeFactory);
        
        unset($routePool);
        
        $this->assertEquals(1, $mediator->countNotifications('__sys.routePool.new'));
    }
    
    /**
     * @covers Artax\Framework\Routing\ObservableRoutePool::addRoute
     * @covers Artax\Framework\Routing\ObservableRoutePool::notify
     */
    public function testAddRouteCallsParentMethodAndNotifiesListeners() {
        $reflCacher = new ReflectionPool;
        $injector   = new Provider($reflCacher);
        $mediator   = new Notifier($injector);
        
        
        $persistence = new StdClass;
        $persistence->test = false;
        
        $mediator->push('__sys.routePool.addRoute', function() use ($persistence) {
            $persistence->test = true;
        });
        
        $routePool = new ObservableRoutePool($mediator, new StdRouteFactory);
        $routePool->addRoute('/widgets', 'WidgetResource');
        
        $this->assertTrue($persistence->test);
    }
    
}
