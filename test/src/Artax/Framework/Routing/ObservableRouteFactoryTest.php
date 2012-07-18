<?php

use Artax\Injection\Provider,
    Artax\Events\Notifier,
    Artax\Injection\ReflectionPool,
    Artax\Framework\Routing\ObservableRouteFactory;

class ObservableRouteFactoryTest extends PHPUnit_Framework_TestCase {

    /**
     * @covers Artax\Framework\Routing\ObservableRouteFactory::__construct
     */
    public function testBeginsEmpty() {
        $reflCacher   = new ReflectionPool;
        $provider     = new Provider($reflCacher);
        $notifier     = new Notifier($provider);
        $routeFactory = new ObservableRouteFactory($notifier);
        
        return $routeFactory;
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Framework\Routing\ObservableRouteFactory::make
     */
    public function testMakeCreatesRoute($routeFactory) {
        $this->assertInstanceOf('Artax\\Framework\\Routing\\ObservableRoute',
            $routeFactory->make('/widgets', 'WidgetController')
        );
    }
}
