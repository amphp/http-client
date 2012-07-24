<?php

use Artax\Injection\Provider,
    Artax\Events\Notifier,
    Artax\Injection\ReflectionPool,
    Artax\Framework\Routing\ObservableResource,
    Artax\Framework\Routing\ObservableResourceFactory;


class ObservableResourceFactoryTest extends PHPUnit_Framework_TestCase {

    /**
     * @covers Artax\Framework\Routing\ObservableResourceFactory::__construct
     */
    public function testConstructorStoresMediatorInstance() {
        $reflCacher = new ReflectionPool;
        $injector   = new Provider($reflCacher);
        $mediator   = new Notifier($injector);
        
        $factory = new ObservableResourceFactory($mediator);
        $this->assertInstanceOf('Artax\\Framework\\Routing\\ObservableResourceFactory', $factory);
        
        return $factory;
    }
    
    /**
     * @depends testConstructorStoresMediatorInstance
     * @covers Artax\Framework\Routing\ObservableResourceFactory::make
     */
    public function testMakeCreatesRoutedRoutedResource($factory) {
        $callableResource = function($arg) { return $arg; };
        $resourceMethodArgs = array(42);
        
        $resource = $factory->make($callableResource, $resourceMethodArgs);
        $this->assertInstanceOf('Artax\\Framework\\Routing\\ObservableResource', $resource);
    }
    
    /**
     * @depends testConstructorStoresMediatorInstance
     * @covers Artax\Framework\Routing\ObservableResourceFactory::make
     * @expectedException InvalidArgumentException
     */
    public function testMakeThrowsExceptionOnUncallableResourceParameter($factory) {
        $resource = $factory->make('not callable', array());
    }
    
}
