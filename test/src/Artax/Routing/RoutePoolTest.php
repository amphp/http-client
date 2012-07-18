<?php

use Artax\Routing\RoutePool,
    Artax\Routing\StdRouteFactory;

class RoutePoolTest extends PHPUnit_Framework_TestCase {
    
    public function provideEmptyRoutePoolInstance() {
        $stdRouteFactory = new StdRouteFactory;
        $routePool = new RoutePool($stdRouteFactory);
        
        return array(
            array($routePool)
        );
    }
    
    /**
     * @covers Artax\Routing\RoutePool::__construct
     */
    public function testBeginsEmpty() {
        $stdRouteFactory = new StdRouteFactory;
        $routePool = new RoutePool($stdRouteFactory);
        $this->assertInstanceOf('Artax\\Routing\\RoutePool', $routePool);
    }
    
    /**
     * @dataProvider provideEmptyRoutePoolInstance
     * @covers Artax\Routing\RoutePool::addRoute
     * @covers Artax\Routing\RoutePool::count
     */
    public function testAddRouteAddsRouteAndReturnsNull($routePool) {
        $this->assertEquals(null, $routePool->addRoute('/', 'IndexController'));
        $this->assertEquals(1, count($routePool));
        $routePool->addRoute('/widgets', 'WidgetController');
        $this->assertEquals(2, count($routePool));
    }
    
    /**
     * @dataProvider provideEmptyRoutePoolInstance
     * @covers Artax\Routing\RoutePool::addAllRoutes
     */
    public function testAddAllAddsRoutesToPoolAndReturnsNull($routePool) {
        $this->assertEquals(0, count($routePool));
        $this->assertEquals(null, $routePool->addAllRoutes(array(
            '/' => 'IndexController',
            '/widgets' => 'WidgetController',
            '/kumqats' => 'KumqatController'
        )));
        $this->assertEquals(3, count($routePool));
    }
    
    /**
     * @dataProvider provideEmptyRoutePoolInstance
     * @covers Artax\Routing\RoutePool::addAllRoutes
     * @expectedException InvalidArgumentException
     */
    public function testAddAllThrowsExceptionOnInvalidIterable($routePool) {
        $routePool->addAllRoutes('not an iterable');
    }
    
    /**
     * @dataProvider provideEmptyRoutePoolInstance
     * @covers Artax\Routing\RoutePool::current
     * @covers Artax\Routing\RoutePool::key
     * @covers Artax\Routing\RoutePool::next
     * @covers Artax\Routing\RoutePool::rewind
     * @covers Artax\Routing\RoutePool::valid
     */
    public function testIterableRoutePoolImplementation($routePool) {
        $routePool->addAllRoutes(array(
            '/widgets' => 'WidgetClass',
            '/kumqats' => 'KumqatClass'
        ));
        foreach ($routePool as $key => $route) {}
    }
    
    /**
     * @dataProvider provideEmptyRoutePoolInstance
     * @covers Artax\Routing\RoutePool::serialize
     * @covers Artax\Routing\RoutePool::unserialize
     */
    public function testSerializationProtocols($routePool) {
        $routePool->addRoute('/kumqats', 'KumqatController');
        
        $serialDup = unserialize(serialize($routePool));
        
        $routePool->rewind();        
        foreach ($serialDup as $route) {
            $origRoute = $routePool->current();
            $this->assertEquals($origRoute, $route);
            $routePool->next();
        }
    }
}
