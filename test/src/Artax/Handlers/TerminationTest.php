<?php

class TerminationTest extends PHPUnit_Framework_TestCase
{
    /**
     * 
     */
    protected function getProvider()
    {
        return new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
    }
    
    /**
     * @covers Artax\Handlers\Termination::__construct
     */
    public function testConstructorInitializesDependencies()
    {
        $dp  = $this->getProvider();
        $med = $this->getMock('Artax\Events\Mediator', NULL, [$dp]);
        $obj = new TerminationTestImplementation($med, TRUE);
        $this->assertTrue($obj->debug);
        $this->assertEquals($med, $obj->mediator);
    }
    
    /**
     * @covers Artax\Handlers\Termination::register
     */
    public function testRegisterReturnsInstanceForChaining()
    {
        $dp  = $this->getProvider();
        $med = $this->getMock('Artax\Events\Mediator', NULL, [$dp]);
        $t = $this->getMock('Artax\Handlers\Termination',
            ['exception', 'shutdown', 'setMediator', 'getFatalErrorException', 'lastError', 'defaultHandlerMsg'],
            [$med, FALSE]
        );
        
        $this->assertEquals($t->register(), $t);
        restore_exception_handler();
    }
    
    /**
     * @covers Artax\Handlers\Termination::shutdown
     * @covers Artax\Handlers\Termination::getFatalErrorException
     * @covers Artax\Handlers\Termination::lastError
     */
    public function testShutdownInvokesExHandlerOnFatalError()
    {
        $lastErr = [
            'type'    => 1,
            'message' => 'The black knight always triumphs!',
            'file'    => '/path/to/file.php',
            'line'    => 42
        ];
        
        $dp  = $this->getProvider();
        $med = $this->getMock('Artax\Events\Mediator', NULL, [$dp]);
        $stub = $this->getMock(
            'TerminationTestImplementation',
            ['exception', 'lastError', 'defaultHandlerMsg'],
            [$med, TRUE]
        );
        $stub->expects($this->any())
                 ->method('lastError')
                 ->will($this->returnValue($lastErr));
        $stub->shutdown();
    }
    
    /**
     * @covers Artax\Handlers\Termination::defaultHandlerMsg
     */
    public function testDefaultHandlerMsgReturnsExpectedString()
    {
        $dp  = $this->getProvider();
        $med = $this->getMock('Artax\Events\Mediator', NULL, [$dp]);
        $obj = new TerminationTestImplementation($med, FALSE);
        ob_start();
        $obj->exception(new \Exception('test'));
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals(NULL, $output);
        
        $obj = new TerminationTestImplementation($med, TRUE);
        ob_start();
        $obj->exception(new \Exception('test'));
        $output = ob_get_contents();
        ob_end_clean();
        
        $this->assertStringStartsWith(
            "exception 'Exception' with message 'test'",
            $output
        );
    }
    
    /**
     * @covers Artax\Handlers\Termination::lastError
     */
    public function testLastErrorReturnsNullOnNoFatalPHPError()
    {
        $dp  = $this->getProvider();
        $med = $this->getMock('Artax\Events\Mediator', NULL, [$dp]);
        $obj = new TerminationTestImplementation($med, TRUE);
        $this->assertEquals(NULL, $obj->getFatalErrorException());
    }
    
    /**
     * @covers Artax\Handlers\Termination::shutdown
     */
    public function testShutdownNotifiesListenersIfMediatorExists()
    {
        $medStub = $this->getMock(
            'Artax\Events\Mediator', ['all', 'keys'], [$this->getProvider()]
        );
        $obj = $this->getMock('TerminationTestImplementation',
            ['getFatalErrorException'], [$medStub, TRUE]
        );
        $obj->expects($this->once())
            ->method('getFatalErrorException')
            ->will($this->returnValue(NULL));      
        $obj->shutdown();
    }
    
    /**
     * @covers Artax\Handlers\Termination::exception
     */
    public function testExHandlerReturnsQuietlyOnPurposefulScriptHalt()
    {
        $dp  = $this->getProvider();
        $med = $this->getMock('Artax\Events\Mediator', NULL, [$dp]);
        $obj = new Artax\Handlers\Termination($med, TRUE);
        $this->assertNull($obj->exception(new Artax\Exceptions\ScriptHaltException));
    }
    
    /**
     * @covers Artax\Handlers\Termination::exception
     */
    public function testExHandlerOutputsDefaultMessageIfNoMediatorExists()
    {
        $dp  = $this->getProvider();
        $med = $this->getMock('Artax\Events\Mediator', NULL, [$dp]);
        $stub = $this->getMock('Artax\Handlers\Termination',
            ['defaultHandlerMsg'], [$med, TRUE]
        );
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->with($this->equalTo(new Exception))
             ->will($this->returnValue(NULL));
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Handlers\Termination::exception
     */
    public function testExHandlerNotifiesMediatorOnUncaughtException()
    {
        $dp  = $this->getProvider();
        $med = $this->getMock('Artax\Events\Mediator', NULL, [$dp]);
        $stub = $this->getMock('Artax\Handlers\Termination',
            ['notify'], [$med, TRUE]
        );
        $stub->expects($this->once())
             ->method('notify')
             ->with($this->equalTo('exception'),
                $this->equalTo(new Exception), $this->equalTo(TRUE)
        );
        
        $med->push('exception', function(){});
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Handlers\Termination::exception
     */
    public function testExHandlerFallsBackToDefaultMessageOnNotifyException()
    {
        $dp  = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $med = new Artax\Events\Mediator($dp);
        $stub = $this->getMock('Artax\Handlers\Termination',
            ['notify', 'defaultHandlerMsg'],
            [$med, TRUE]
        );
        $stub->expects($this->once())
             ->method('notify')
             ->will($this->throwException(new Exception));
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->with($this->equalTo(new Exception))
             ->will($this->returnValue(NULL));
        
        $med->push('exception', function(){});
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Handlers\Termination::exception
     */
    public function testExHandlerReturnsImmediatelyOnNotifyScriptHaltException()
    {
        $e = new Artax\Exceptions\ScriptHaltException;
        
        $dp  = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $med = new Artax\Events\Mediator($dp);
        $stub = $this->getMock('Artax\Handlers\Termination',
            ['notify'], [$med, TRUE]
        );
        $stub->expects($this->once())
             ->method('notify')
             ->will($this->throwException($e));
        
        // We have to push a listener otherwise notify won't be called
        $med->push('exception', function(){});
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Handlers\Termination::shutdown
     */
    public function testShutdownReturnsImmediatelyOnNotifyScriptHaltException()
    {
        $dp  = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $med = new Artax\Events\Mediator($dp);
        $stub = $this->getMock('Artax\Handlers\Termination',
            ['notify'], [$med, TRUE]
        );
        $stub->expects($this->once())
             ->method('notify')
             ->will($this->throwException(new Artax\Exceptions\ScriptHaltException));
        
        // We have to push a listener otherwise notify won't be called
        $med->push('exception', function(){});
        $this->assertNull($stub->shutdown());
    }
    
    /**
     * @covers Artax\Handlers\Termination::shutdown
     */
    public function testShutdownFallsBackToDefaultOnNotifyException()
    {
        $dp  = new Artax\Ioc\Provider(new Artax\Ioc\DotNotation);
        $med = new Artax\Events\Mediator($dp);
        $stub = $this->getMock('Artax\Handlers\Termination',
            ['notify', 'defaultHandlerMsg'], [$med, TRUE]
        );
        $stub->expects($this->once())
             ->method('notify')
             ->will($this->throwException(new Exception));
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->will($this->returnValue('test exception output'));
        
        // We have to push a listener otherwise notify won't be called
        $med->push('exception', function(){});
        
        $this->expectOutputString('test exception output');
        $stub->shutdown();
    }
}



class TerminationTestImplementation extends Artax\Handlers\Termination
{
    use MagicTestGetTrait;
}
