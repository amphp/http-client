<?php

use Artax\Injection\ReflectionPool,
    Artax\Injection\Provider,
    Artax\Events\Notifier,
    Artax\Framework\Routing\ObservableResourceFactory,
    Artax\Framework\Routing\ClassResourceMapper,
    Artax\Framework\Routing\BadResourceClassException,
    Artax\Framework\Routing\BadResourceMethodException;

class ClassResourceMapperTest extends PHPUnit_Framework_TestCase {

    /**
     * @covers Artax\Framework\Routing\ClassResourceMapper::__construct
     */
    public function testBeginsEmpty() {
        $reflCacher = new ReflectionPool;
        $injector = new Provider($reflCacher);
        $mediator = new Notifier($injector);
        $resFactory = new ObservableResourceFactory($mediator);
        $resMapper = new ClassResourceMapper($injector, $reflCacher, $resFactory);
        
        return $resMapper;
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Framework\Routing\ClassResourceMapper::make
     * @covers Artax\Framework\Routing\ClassResourceMapper::provisionResource
     * @expectedException Artax\Framework\Routing\BadResourceClassException
     */
    public function testMakeThrowsExceptionOnResourceClassLoadFailure($resMapper) {
        $resMapper->make('NonexistentResourceClass', 'get', array());
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Framework\Routing\ClassResourceMapper::make
     * @expectedException Artax\Framework\Routing\BadResourceMethodException
     */
    public function testMakeThrowsExceptionOnBadResourceMethodVerb($resMapper) {
        $resMapper->make('WidgetController', 'post', array());
    }
    
    /**
     * @depends testBeginsEmpty
     * @covers Artax\Framework\Routing\ClassResourceMapper::make
     * @covers Artax\Framework\Routing\ClassResourceMapper::provisionResource
     * @covers Artax\Framework\Routing\ClassResourceMapper::mergeMethodArgs
     */
    public function testMakeReturnsNewResourceInstance($resMapper) {
        $resource = $resMapper->make('WidgetController2', 'get', array('arg1'=>1));
        $this->assertInstanceOf('Artax\\Framework\\Routing\\ObservableResource', $resource);
    }
}

class WidgetController {
    public function __construct(){}
    public function get(){}
}
class WidgetController2 {
    public function get($arg1, TypehintDep $arg2, $arg3, $arg4 = 42){}
}
class TypehintDep {}
