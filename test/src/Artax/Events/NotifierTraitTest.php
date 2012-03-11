<?php

class NotifierTraitTest extends PHPUnit_Framework_TestCase
{
    
    /**
     * @covers Artax\Events\NotifierTrait::notify
     */
    public function testNotifySendsMessageToMediatorIfSet()
    {
        $stub = $this->getMock('Artax\Events\Mediator', ['notify'],
                [new Artax\Ioc\Provider(new Artax\Ioc\DotNotation)]
        );
        $stub->expects($this->once())
             ->method('notify')
             ->will($this->returnArgument(0));
            
        $n = new NotifierTraitImplementationClass($stub);
        $this->assertEquals('test.event', $n->notify('test.event'));
    }
    
    /**
     * @covers Artax\Events\NotifierTrait::notify
     */
    public function testNotifySendsObjectInstanceOnNullDataParameter()
    {
        $stub = $this->getMock('Artax\Events\Mediator', ['notify'],
                [new Artax\Ioc\Provider(new Artax\Ioc\DotNotation)]
        );
        $stub->expects($this->once())
             ->method('notify')
             ->will($this->returnArgument(1));
            
        $n = new NotifierTraitImplementationClass($stub);
        $this->assertEquals($n, $n->notify('test.event'));
    }
    
    /**
     * @covers Artax\Events\NotifierTrait::notify
     */
    public function testNotifySendsParamsIfDataParametersPassed()
    {
        $stub = $this->getMock('Artax\Events\Mediator', ['notify'],
                [new Artax\Ioc\Provider(new Artax\Ioc\DotNotation)]
        );
        $stub->expects($this->once())
             ->method('notify')
             ->will($this->returnArgument(1));
            
        $n = new NotifierTraitImplementationClass($stub);
        $n->notify('test.event', 1, 2, 3);
    }
    
    /**
     * @covers Artax\Events\NotifierTrait::notify
     */
    public function testNotifyDoesNothingIfMediatorIsNull()
    {
        $n = new NotifierTraitImplementationClass;
        $this->assertNull($n->notify('test.event'));
    }
}

class NotifierTraitImplementationClass
{
    use Artax\Events\NotifierTrait;
    
    public function __construct($mediator=NULL)
    {
        $this->mediator = $mediator;
    }
}
