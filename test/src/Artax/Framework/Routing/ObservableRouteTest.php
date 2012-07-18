<?php

use Artax\Injection\Provider,
    Artax\Events\Notifier,
    Artax\Injection\ReflectionPool,
    Artax\Framework\Routing\ObservableRoute;

class ObservableRouteTest extends PHPUnit_Framework_TestCase {
    
    public function provideRouteParameters() {
        return array(
            array('/widgets', 'WidgetListResource'),
            array('/kumqats/42', 'SpecificKumqatResource')
        );
    }
    
    /**
     * @dataProvider provideRouteParameters
     * @covers Artax\Framework\Routing\ObservableRoute::__construct
     * @covers Artax\Framework\Routing\ObservableRoute::notify
     */
    public function testConstructorAssignsPropertiesAndNotifiesOnCreation($uriPattern, $resource) {
        $reflCacher = new ReflectionPool;
        $injector   = new Provider($reflCacher);
        $mediator   = new Notifier($injector);
        
        $route = new ObservableRoute($mediator, 'widgets', 'WidgetResource');
        
        $this->assertEquals('/widgets', $route->getPattern());
        $this->assertEquals(1, $mediator->countNotifications('__sys.route.new'));
        
        return $route;
    }
    
    /**
     * @dataProvider provideRouteParameters
     * @covers Artax\Framework\Routing\ObservableRoute::setResource
     * @covers Artax\Framework\Routing\ObservableRoute::notify
     */
    public function testSetResourceNotifiesListenersAndReturnsNull($uriPattern, $resource) {
        $reflCacher = new ReflectionPool;
        $injector   = new Provider($reflCacher);
        $mediator   = new Notifier($injector);
        
        $route = new ObservableRoute($mediator, $uriPattern, $resource);
        
        $this->assertEquals(null, $route->setResource('SomeOtherResource'));
        $this->assertEquals('SomeOtherResource', $route->getResource());
        
        $this->assertEquals(2, $mediator->countNotifications('__sys.route.setResource'));
    }
    
    /**
     * @dataProvider provideRouteParameters
     * @covers Artax\Framework\Routing\ObservableRoute::setPattern
     * @covers Artax\Framework\Routing\ObservableRoute::notify
     */
    public function testSetPatternAssignsProperty($uriPattern, $resource) {
        $reflCacher = new ReflectionPool;
        $injector   = new Provider($reflCacher);
        $mediator   = new Notifier($injector);
        
        $route = new ObservableRoute($mediator, $uriPattern, $resource);
        
        $this->assertEquals(null, $route->setPattern('/widgets-transformed'));
        $this->assertEquals('/widgets-transformed', $route->getPattern());
        
        $this->assertEquals(2, $mediator->countNotifications('__sys.route.setPattern'));
    }
}
