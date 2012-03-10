<?php

class BootstrapperTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\Bootstrapper::__construct
     */
    public function testConstructorAssignsProperties()
    {
        $med  = new Artax\Events\Mediator;
        $term = new BootstrapperTerminationHandlerTestClass;
        $boot = new BootstrapperTestImplementationClass($term, $med);
        
        $this->assertEquals($med, $boot->mediator);
        $this->assertEquals($term, $boot->termination);
        
        return $boot;
    }
    
    /**
     * @depends testConstructorAssignsProperties
     * @covers  Artax\Bootstrapper::boot
     */
    public function testBootInjectsMediatorIntoTermHandlerAndReturnsMediator($boot)
    {
        $this->assertEquals($boot->mediator, $boot->boot());
    }
      
}

class BootstrapperTestImplementationClass extends Artax\Bootstrapper
{
    use MagicTestGetTrait;
}

class BootstrapperTerminationHandlerTestClass extends Artax\Handlers\Termination
{
    use MagicTestGetTrait;
}
