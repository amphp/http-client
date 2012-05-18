<?php

use Artax\Core\Provider,
    Artax\Core\FatalErrorException,
    Artax\Core\Handlers,
    Artax\Core\ScriptHaltException;

class HandlersTest extends PHPUnit_Framework_TestCase
{
    /**
     * 
     */
    public function tearDown()
    {
        restore_error_handler();
        restore_exception_handler();
    }
    
    /**
     * @covers Artax\Core\Handlers::__construct
     */
    public function testBeginsEmpty()
    {
        $dp  = new Provider;
        
        $med = $this->getMock('Artax\Core\Mediator', NULL, [$dp]);
        $obj = new Handlers(TRUE, $med);
    }
    
    /**
     * @covers Artax\Core\Handlers::register
     */
    public function testRegisterReturnsInstanceForChaining()
    {
        $dp = new Provider;
        $med = $this->getMock('Artax\Core\Mediator', NULL, [$dp]);
        $t = $this->getMock('Artax\Core\Handlers', ['exception',
            'shutdown', 'setMediator', 'getFatalErrorException', 'lastError',
            'defaultHandlerMsg'
            ],
            [FALSE, $med]
        );
        
        $this->assertEquals($t->register(), $t);
    }
    
    /**
     * @covers Artax\Core\Handlers::error
     */
    public function testErrorNotifiesMediatorOnError()
    {
        ob_start();
        $msg = 'test notice message in testFile.php on line 42';
        $ex  = new ErrorException($msg, E_NOTICE);
        
        $dp  = new Provider;
        $med = $this->getMock('Artax\Core\Mediator', ['notify'], [$dp]);
        $med->expects($this->once())
            ->method('notify')
            ->with('error', $ex, TRUE);
        
        $stub = $this->getMock('Artax\Core\Handlers', ['exception'], [TRUE, $med]);
        $stub->error(E_NOTICE, 'test notice message', 'testFile.php', 42);
        ob_end_clean();
    }
    
    /**
     * @covers Artax\Core\Handlers::error
     * @covers Artax\Core\Handlers::shutdown
     * @expectedException Artax\Core\ScriptHaltException
     * @covers Artax\Core\ScriptHaltException
     */
    public function testErrorChainsOnException()
    {
        $dp  = new Provider;
        $med = $this->getMock('Artax\Core\Mediator', ['notify'], [$dp]);
        $med->expects($this->once())
            ->method('notify')
            ->will($this->throwException(new Exception));
        
        $stub = $this->getMock('Artax\Core\Handlers', ['exception'], [TRUE, $med]);
        $stub->error(E_NOTICE, 'test notice message', 'testFile.php', 42);
    }
    
    /**
     * @covers Artax\Core\Handlers::shutdown
     * @covers Artax\Core\Handlers::getFatalErrorException
     * @covers Artax\Core\Handlers::lastError
     */
    public function testShutdownInvokesExHandlerOnFatalError()
    {
        $lastErr = [
            'type'    => 1,
            'message' => 'The black knight always triumphs!',
            'file'    => '/path/to/file.php',
            'line'    => 42
        ];
        
        $dp   = new Provider;
        $med  = $this->getMock('Artax\Core\Mediator', NULL, [$dp]);
        $stub = $this->getMock(
            'Artax\Core\Handlers',
            ['exception', 'lastError', 'defaultHandlerMsg'],
            [TRUE, $med]
        );
        $stub->expects($this->any())
                 ->method('lastError')
                 ->will($this->returnValue($lastErr));
        $stub->shutdown();
    }
    
    /**
     * @covers Artax\Core\Handlers::defaultHandlerMsg
     */
    public function testDefaultHandlerMsgReturnsExpectedString()
    {
        $dp  = new Provider;
        
        $med = $this->getMock('Artax\Core\Mediator', NULL, [$dp]);
        
        $obj = new Handlers(FALSE, $med);
        
        ob_start();
        $obj->exception(new Exception('test'));
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals(NULL, $output);
        
        $obj = new Handlers(TRUE, $med);
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
     * @covers Artax\Core\Handlers::lastError
     */
    public function testLastErrorReturnsNullOnNoFatalPHPError()
    {
        $dp  = new Provider;
        
        $med = $this->getMock('Artax\Core\Mediator', NULL, [$dp]);
        $obj = new Handlers(TRUE, $med);
        $this->assertEquals(NULL, $obj->getFatalErrorException());
    }
    
    /**
     * @covers Artax\Core\Handlers::shutdown
     */
    public function testShutdownNotifiesListenersIfMediatorExists()
    {
        
        $medStub = $this->getMock(
            'Artax\Core\Mediator', ['all', 'keys'], [new Provider]
        );
        $obj = $this->getMock('Artax\Core\Handlers',
            ['getFatalErrorException'], [TRUE, $medStub]
        );
        $obj->expects($this->once())
            ->method('getFatalErrorException')
            ->will($this->returnValue(NULL));      
        $obj->shutdown();
    }
    
    /**
     * @covers Artax\Core\Handlers::exception
     */
    public function testExHandlerReturnsQuietlyOnPurposefulScriptHalt()
    {
        
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Provider]
        );
        $obj = new Handlers(TRUE, $med);
        $this->assertNull($obj->exception(new ScriptHaltException));
    }
    
    /**
     * @covers Artax\Core\Handlers::exception
     */
    public function testExHandlerOutputsDefaultMessageIfNoMediatorExists()
    {
        
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers',
            ['defaultHandlerMsg'], [TRUE, $med]
        );
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->with($this->equalTo(new Exception))
             ->will($this->returnValue(NULL));
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Core\Handlers::exception
     */
    public function testExHandlerNotifiesMediatorOnUncaughtException()
    {
        
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers',
            NULL, [TRUE, $med]
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
     * @covers Artax\Core\Handlers::exception
     */
    public function testExHandlerFallsBackToDefaultMessageOnNotifyException()
    {
        
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Provider]
        );
        $med->expects($this->once())
             ->method('notify')
             ->will($this->throwException(new Exception));
        $stub = $this->getMock('Artax\Core\Handlers',
            ['notify', 'defaultHandlerMsg'],
            [TRUE, $med]
        );
        
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->with($this->equalTo(new Exception))
             ->will($this->returnValue(NULL));
        
        $med->push('exception', function(){});
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Core\Handlers::exception
     */
    public function testExHandlerReturnsImmediatelyOnNotifyScriptHaltException()
    {
        $e   = new ScriptHaltException;
        
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers',
            ['notify'], [TRUE, $med]
        );
        $med->expects($this->once())
             ->method('notify')
             ->will($this->throwException($e));
        
        // We have to push a listener otherwise notify won't be called
        $med->push('exception', function(){});
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Core\Handlers::shutdown
     */
    public function testShutdownReturnsImmediatelyOnNotifyScriptHaltException()
    {
        
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers', NULL, [TRUE, $med]);
        $med->expects($this->once())
             ->method('notify')
             ->will($this->throwException(new ScriptHaltException));
        
        // We have to push a listener otherwise notify won't be called
        $med->push('exception', function(){});
        $this->assertNull($stub->shutdown());
    }
    
    /**
     * @covers Artax\Core\Handlers::shutdown
     */
    public function testShutdownFallsBackToDefaultOnNotifyException()
    {
        
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers',
            ['defaultHandlerMsg'], [TRUE, $med]
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
     * @covers Artax\Core\Handlers::exception
     */
    public function testExHandlerNotifiesShutdownEventOnFatalRuntimeError()
    {
        ob_start();
        
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers',
            NULL, [TRUE, $med]
        );
        $med->expects($this->exactly(2))
             ->method('notify')
             ->will($this->returnValue(NULL));
             
        // We have to push listeners otherwise notify won't be called
        $med->push('exception', function(){});
        $med->push('shutdown', function(){});
        $stub->exception(new FatalErrorException);
        ob_end_clean();
    }
    
    /**
     * @covers Artax\Core\Handlers::exception
     */
    public function testExHandlerFallsBackToDefaultOnNotifyException()
    {
        
        $med = $this->getMock('Artax\Core\Mediator', ['notify'],
            [new Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers',
            ['defaultHandlerMsg'], [TRUE, $med]
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
        $stub->exception(new FatalErrorException);
    }
    
    /**
     * @covers Artax\Core\Handlers::exception
     */
    public function testExHandlerExitsOnNotifyScriptHaltException()
    {
        
        $med = $this->getMock('Artax\Core\Mediator', ['notify'], 
            [new Provider]
        );
        $stub = $this->getMock('Artax\Core\Handlers',
            NULL, [TRUE, $med]
        );
        $med->expects($this->atLeastOnce())
             ->method('notify')
             ->will($this->throwException(new ScriptHaltException));
             
        // We have to push listeners otherwise notify won't be called
        $med->push('exception', function(){});
        $med->push('shutdown', function(){});
        $this->assertNull($stub->exception(new FatalErrorException));
    }
}
