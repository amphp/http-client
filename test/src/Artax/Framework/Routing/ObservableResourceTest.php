<?php

use Artax\Injection\ReflectionPool,
    Artax\Injection\Provider,
    Artax\Events\Notifier,
    Artax\Framework\Routing\ObservableResource;

class ObservableResourceTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Routing\ObservableResource::__construct
     * @covers Artax\Framework\Routing\ObservableResource::notify
     */
    public function testBeginsEmptyAndNotifiesListenersOnNewConstruction() {
        $reflCacher = new ReflectionPool;
        $injector   = new Provider($reflCacher);
        $mediator   = new Notifier($injector);
        
        $resource = new ObservableResource($mediator, function(){}, array());        
        $this->assertInstanceOf('Artax\\Framework\\Routing\\ObservableResource', $resource);
        $this->assertEquals(1, $mediator->countNotifications('__sys.resource.new'));
    }
    
    /**
     * @covers Artax\Framework\Routing\ObservableResource::__invoke
     */
    public function testMagicInvokeCallsResourceAndNotifiesListeners() {
        $reflCacher = new ReflectionPool;
        $injector   = new Provider($reflCacher);
        $mediator   = new Notifier($injector);
        
        $resource = new ObservableResource(
            $mediator,
            function($arg1, $arg2){ return $arg1 - $arg2; },
            array(42, 40)
        );
        
        $this->assertEquals(null, $resource());
        $this->assertEquals(1, $mediator->countNotifications('__sys.resource.beforeInvocation'));
        $this->assertEquals(1, $mediator->countNotifications('__sys.resource.afterInvocation'));
    }
    
    /**
     * @covers Artax\Framework\Routing\ObservableResource::getCallableResource
     */
    public function testCallableResourceGetter() {
        $mediator   = $this->getMock('Artax\\Events\\Mediator');
        $callableResource = function($arg1, $arg2){ return $arg1 - $arg2; };
        $invocationArgs = array(42, 40);
        $resource = new ObservableResource($mediator, $callableResource, $invocationArgs);
        
        $this->assertEquals($callableResource, $resource->getCallableResource());
    }
    
    /**
     * @covers Artax\Framework\Routing\ObservableResource::getInvocationArgs
     */
    public function testInvocationArgsGetter() {
        $mediator   = $this->getMock('Artax\\Events\\Mediator');
        $callableResource = function($arg1, $arg2){ return $arg1 - $arg2; };
        $invocationArgs = array(42, 40);
        $resource = new ObservableResource($mediator, $callableResource, $invocationArgs);
        
        $this->assertEquals($invocationArgs, $resource->getInvocationArgs());
    }
    
    /**
     * @covers Artax\Framework\Routing\ObservableResource::getInvocationResult
     */
    public function testInvocationResultGetter() {
        
        $mediator   = $this->getMock('Artax\\Events\\Mediator');
        $callableResource = function($arg1, $arg2){ return $arg1 - $arg2; };
        $invocationArgs = array(42, 40);
        
        $resource = new ObservableResource($mediator, $callableResource, $invocationArgs);
        
        $this->assertNull($resource->getInvocationResult());
        $resource();
        $this->assertEquals(2, $resource->getInvocationResult());
    }
}
