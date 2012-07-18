<?php

use Artax\Routing\StdRouteFactory;

class StdRouteFactoryTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Routing\StdRouteFactory::make
     */
    public function testMakeCreatesRoute() {
        $routeFactory = new StdRouteFactory();
        $this->assertInstanceOf('Artax\\Routing\\StdRoute',
            $routeFactory->make('/widgets', 'WidgetController')
        );
    }
}
