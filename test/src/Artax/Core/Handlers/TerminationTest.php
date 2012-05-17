<?php

class TerminationTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\Core\Handlers\Termination::__construct
     */
    public function testConstructorInitializesDependencies()
    {
        $dp  = new Artax\Core\Provider;
        $med = $this->getMock('Artax\Core\Mediator', NULL, [$dp]);
        $obj = new TerminationTestImplementation($med, TRUE);
        $this->assertTrue($obj->debug);
        $this->assertEquals($med, $obj->mediator);
    }
    
    /**
     * @covers Artax\Core\Handlers\Termination::register
     */
    public function testRegisterReturnsInstanceForChaining()
    {
        $dp = new Artax\Core\Provider;
        $med = $this->getMock('Artax\Core\Mediator', NULL, [$dp]);
        $t = $this->getMock('Artax\Core\Handlers\Termination',
            ['exception', 'shutdown', 'setMediator', 'getFatalErrorException', 'lastError', 'defaultHandlerMsg'],
            [$med, FALSE]
        );
        
        $this->assertEquals($t->register(), $t);
        restore_exception_handler();
    }
    
    /**
     * @covers Artax\Core\Handlers\Termination::shutdown
     * @covers Artax\Core\Handlers\Termination::getFatalErrorException
     * @covers Artax\Core\Handlers\Termination::lastError
     */
    public function testShutdownInvokesExHandlerOnFatalError()
    {
        $lastErr = [
            'type'    => 1,
            'message' => 'The black knight always triumphs!',
            'file'    => '/path/to/file.php',
            'line'    => 42
        ];
        
        $dp = new Artax\Core\Provider;
        $med = $this->getMock('Artax\Core\Mediator', NULL, [$dp]);
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
     * @covers Artax\Core\Handlers\Termination::defaultHandlerMsg
     */
    public function testDefaultHandlerMsgReturnsExpectedString()
    {
        $dp = new Artax\Core\Provider;
        $med = $this->getMock('Artax\Core\Mediator', NULL, [$dp]);
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
     * @covers Artax\Core\Handlers\Termination::lastError
     */
    public function testLastErrorReturnsNullOnNoFatalPHPError()
    {
        $dp = new Artax\Core\Provider;
        $med = $this->getMock('Artax\Core\Mediator', NULL, [$dp]);
        $obj = new TerminationTestImplementation($med, TRUE);
        $this->assertEquals(NULL, $obj->getFatalErrorException());
    }
    
    /**
     * @covers Artax\Core\Handlers\Termination::shutdown
     */
    public function testShutdownNotifiesListenersIfMediatorExists()
    {
        $medStub = $this->getMock(
            'Artax\Core\Mediator', ['all', 'keys'], [new Artax\Core\Provider]
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
     * @covers Artax\Core\Handlers\Termination::exception
     */
    public function testExHandlerReturnsQuietlyOnPurposefulScriptHalt()
    {
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Artax\Core\Provider]
        );
        $obj = new Artax\Core\Handlers\Termination($med, TRUE);
        $this->assertNull($obj->exception(new Artax\Core\Handlers\ScriptHaltException));
    }
    
    /**
     * @covers Artax\Core\Handlers\Termination::exception
     */
    public function testExHandlerOutputsDefaultMessageIfNoMediatorExists()
    {
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Artax\Core\Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers\Termination',
            ['defaultHandlerMsg'], [$med, TRUE]
        );
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->with($this->equalTo(new Exception))
             ->will($this->returnValue(NULL));
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Core\Handlers\Termination::exception
     */
    public function testExHandlerNotifiesMediatorOnUncaughtException()
    {
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Artax\Core\Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers\Termination',
            NULL, [$med, TRUE]
        );
        $med->expects($this->once())
             ->method('notify')
             ->with(
                $this->equalTo('exception'),
                $this->equalTo(new Exception),
                $this->equalTo(TRUE)
            );
        
        $med->push('exception', function(){});
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Core\Handlers\Termination::exception
     */
    public function testExHandlerFallsBackToDefaultMessageOnNotifyException()
    {
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Artax\Core\Provider]
        );
        $med->expects($this->once())
             ->method('notify')
             ->will($this->throwException(new Exception));
        $stub = $this->getMock('Artax\Core\Handlers\Termination',
            ['notify', 'defaultHandlerMsg'],
            [$med, TRUE]
        );
        
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->with($this->equalTo(new Exception))
             ->will($this->returnValue(NULL));
        
        $med->push('exception', function(){});
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Core\Handlers\Termination::exception
     */
    public function testExHandlerReturnsImmediatelyOnNotifyScriptHaltException()
    {
        $e = new Artax\Core\Handlers\ScriptHaltException;
        
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Artax\Core\Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers\Termination',
            ['notify'], [$med, TRUE]
        );
        $med->expects($this->once())
             ->method('notify')
             ->will($this->throwException($e));
        
        // We have to push a listener otherwise notify won't be called
        $med->push('exception', function(){});
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Core\Handlers\Termination::shutdown
     */
    public function testShutdownReturnsImmediatelyOnNotifyScriptHaltException()
    {
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Artax\Core\Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers\Termination', NULL, [$med, TRUE]);
        $med->expects($this->once())
             ->method('notify')
             ->will($this->throwException(new Artax\Core\Handlers\ScriptHaltException));
        
        // We have to push a listener otherwise notify won't be called
        $med->push('exception', function(){});
        $this->assertNull($stub->shutdown());
    }
    
    /**
     * @covers Artax\Core\Handlers\Termination::shutdown
     */
    public function testShutdownFallsBackToDefaultOnNotifyException()
    {
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Artax\Core\Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers\Termination',
            ['defaultHandlerMsg'], [$med, TRUE]
        );
        $med->expects($this->once())
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
    
    /**
     * @covers Artax\Core\Handlers\Termination::exception
     */
    public function testExHandlerNotifiesShutdownEventOnFatalRuntimeError()
    {
        ob_start();
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Artax\Core\Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers\Termination',
            NULL, [$med, TRUE]
        );
        $med->expects($this->exactly(2))
             ->method('notify')
             ->will($this->returnValue(NULL));
             
        // We have to push listeners otherwise notify won't be called
        $med->push('exception', function(){});
        $med->push('shutdown', function(){});
        $stub->exception(new Artax\Core\Handlers\FatalErrorException);
        ob_end_clean();
    }
    
    /**
     * @covers Artax\Core\Handlers\Termination::exception
     */
    public function testExHandlerFallsBackToDefaultOnNotifyException()
    {
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Artax\Core\Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers\Termination',
            ['defaultHandlerMsg'], [$med, TRUE]
        );
        $med->expects($this->atLeastOnce())
             ->method('notify')
             ->will($this->throwException(new Exception('test exception output')));
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->will($this->returnValue('test exception output'));
             
        // We have to push listeners otherwise notify won't be called
        $med->push('exception', function(){});
        $med->push('shutdown', function(){});
        
        
        $this->expectOutputString('test exception output');
        $stub->exception(new Artax\Core\Handlers\FatalErrorException);
    }
    
    /**
     * @covers Artax\Core\Handlers\Termination::exception
     */
    public function testExHandlerExitsOnNotifyScriptHaltException()
    {
        $med = $this->getMock('Artax\Core\Mediator', ['notify'], 
            [new Artax\Core\Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers\Termination',
            NULL, [$med, TRUE]
        );
        $med->expects($this->atLeastOnce())
             ->method('notify')
             ->will($this->throwException(new Artax\Core\Handlers\ScriptHaltException));
             
        // We have to push listeners otherwise notify won't be called
        $med->push('exception', function(){});
        $med->push('shutdown', function(){});
        $this->assertNull($stub->exception(new Artax\Core\Handlers\FatalErrorException));
    }
}



class TerminationTestImplementation extends Artax\Core\Handlers\Termination
{
    use MagicTestGetTrait;
}
