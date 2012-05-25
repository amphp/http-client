<?php

use Artax\Provider,
    Artax\FatalErrorException,
    Artax\Handler,
    Artax\ScriptHaltException,
    Artax\ReflectionCache;

class HandlerTest extends PHPUnit_Framework_TestCase
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
     * @covers Artax\Handler::__construct
     */
    public function testBeginsEmpty()
    {
        $dp  = new Provider(new ReflectionCache);
        $med = $this->getMock('Artax\Mediator', NULL,  array($dp));
        $obj = new Handler(1, $med);
    }
    
    /**
     * @covers Artax\Handler::register
     */
    public function testRegisterReturnsInstanceForChaining()
    {
        $dp  = new Provider(new ReflectionCache);
        $med = $this->getMock('Artax\Mediator', NULL,  array($dp));
        $t = $this->getMock('Artax\Handler', array('exception',
            'shutdown', 'setMediator', 'getFatalErrorException', 'lastError',
            'defaultHandlerMsg'
            ),
            array(0, $med)
        );
        
        $this->assertEquals($t->register(), $t);
    }
    
    /**
     * @covers Artax\Handler::error
     */
    public function testErrorNotifiesMediatorOnError()
    {
        ob_start();
        $msg = 'test notice message in testFile.php on line 42';
        $ex  = new ErrorException($msg, E_NOTICE);
        
        $dp  = new Provider(new ReflectionCache);
        $med = $this->getMock('Artax\Mediator', array('notify'),  array($dp));
        $med->expects($this->once())
            ->method('notify')
            ->with('error', $ex, 1);
        
        $stub = $this->getMock('Artax\Handler', array('exception'), array(1, $med));
        $stub->error(E_NOTICE, 'test notice message', 'testFile.php', 42);
        ob_end_clean();
    }
    
    /**
     * @covers Artax\Handler::error
     * @covers Artax\Handler::shutdown
     * @expectedException Artax\ScriptHaltException
     * @covers Artax\ScriptHaltException
     */
    public function testErrorChainsOnExceptionAndClearsBufferedOutput()
    {
        $dp  = new Provider(new ReflectionCache);
        $med = $this->getMock('Artax\Mediator', array('notify'),  array($dp));
        ob_start();
        echo 'test';
        $med->expects($this->once())
            ->method('notify')
            ->will($this->throwException(new Exception));
        
        $stub = $this->getMock('Artax\Handler', array('exception'), array(1, $med));
        $stub->error(E_NOTICE, 'test notice message', 'testFile.php', 42);
    }
    
    /**
     * @covers Artax\Handler::shutdown
     * @covers Artax\Handler::getFatalErrorException
     * @covers Artax\Handler::lastError
     */
    public function testShutdownInvokesExHandlerOnFatalError()
    {
        $lastErr = array(
            'type'    => 1,
            'message' => 'The black knight always triumphs!',
            'file'    => '/path/to/file.php',
            'line'    => 42
        );
        
        $dp   = new Provider(new ReflectionCache);
        $med  = $this->getMock('Artax\Mediator', NULL,  array($dp));
        $stub = $this->getMock(
            'Artax\Handler',
            array('exception', 'lastError', 'defaultHandlerMsg'),
            array(1, $med)
        );
        $stub->expects($this->any())
                 ->method('lastError')
                 ->will($this->returnValue($lastErr));
        $stub->shutdown();
    }
    
    /**
     * @covers Artax\Handler::defaultHandlerMsg
     */
    public function testDefaultHandlerMsgReturnsExpectedString()
    {
        $dp  = new Provider(new ReflectionCache);
        
        $med = $this->getMock('Artax\Mediator', NULL,  array($dp));
        
        $obj = new Handler(0, $med);
        
        ob_start();
        $obj->exception(new Exception('test'));
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals(NULL, $output);
        
        $obj = new Handler(1, $med);
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
     * @covers Artax\Handler::lastError
     */
    public function testLastErrorReturnsNullOnNoFatalPHPError()
    {
        $dp  = new Provider(new ReflectionCache);
        
        $med = $this->getMock('Artax\Mediator', NULL,  array($dp));
        $obj = new Handler(1, $med);
        $this->assertEquals(NULL, $obj->getFatalErrorException());
    }
    
    /**
     * @covers Artax\Handler::shutdown
     */
    public function testShutdownNotifiesListenersIfMediatorExists()
    {
        
        $medStub = $this->getMock(
            'Artax\Mediator', array('all', 'keys'),
            array(new Provider(new ReflectionCache))
        );
        $obj = $this->getMock('Artax\Handler',
            array('getFatalErrorException'), array(1, $medStub)
        );
        $obj->expects($this->once())
            ->method('getFatalErrorException')
            ->will($this->returnValue(NULL));      
        $obj->shutdown();
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExHandlerReturnsQuietlyOnPurposefulScriptHalt()
    {
        
        $med = $this->getMock('Artax\Mediator', array('notify'),
            array(new Provider(new ReflectionCache))
        );
        $obj = new Handler(1, $med);
        $this->assertNull($obj->exception(new ScriptHaltException));
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExHandlerClearsBufferOnFatalException()
    {
        $med = $this->getMock('Artax\Mediator', array('notify'),
            array(new Provider(new ReflectionCache))
        );
        $obj = new Handler(1, $med);
        ob_start();
        echo 'test';
        $this->assertNull($obj->exception(new Exception));
        ob_end_clean();
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExHandlerChangesErrorReportingOnFatalException()
    {
        $med = $this->getMock('Artax\Mediator', array('notify'),
            array(new Provider(new ReflectionCache))
        );
        $obj = new Handler(1, $med);
        ob_start();
        $this->assertNull($obj->exception(new FatalErrorException));
        ob_end_clean();
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExHandlerOutputsDefaultMessageIfNoMediatorExists()
    {
        
        $med = $this->getMock('Artax\Mediator', array('notify'),
            array(new Provider(new ReflectionCache))
        );
        $stub = $this->getMock('Artax\Handler',
            array('defaultHandlerMsg'), array(1, $med)
        );
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->with($this->equalTo(new Exception))
             ->will($this->returnValue(NULL));
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExHandlerNotifiesMediatorOnUncaughtException()
    {
        
        $med = $this->getMock('Artax\Mediator', array('notify'),
            array(new Provider(new ReflectionCache))
        );
        $stub = $this->getMock('Artax\Handler',
            NULL, array(1, $med)
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
     * @covers Artax\Handler::exception
     */
    public function testExHandlerFallsBackToDefaultMessageOnNotifyException()
    {
        
        $med = $this->getMock('Artax\Mediator', array('notify'),
            array(new Provider(new ReflectionCache))
        );
        $med->expects($this->once())
             ->method('notify')
             ->will($this->throwException(new Exception));
        $stub = $this->getMock('Artax\Handler',
            array('notify', 'defaultHandlerMsg'),
            array(1, $med)
        );
        
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->with($this->equalTo(new Exception))
             ->will($this->returnValue(NULL));
        
        $med->push('exception', function(){});
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExHandlerReturnsImmediatelyOnNotifyScriptHaltException()
    {
        $e   = new ScriptHaltException;
        
        $med = $this->getMock('Artax\Mediator', array('notify'),
            array(new Provider(new ReflectionCache))
        );
        $stub = $this->getMock('Artax\Handler',
            array('notify'), array(1, $med)
        );
        $med->expects($this->once())
             ->method('notify')
             ->will($this->throwException($e));
        
        // We have to push a listener otherwise notify won't be called
        $med->push('exception', function(){});
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Handler::shutdown
     */
    public function testShutdownReturnsImmediatelyOnNotifyScriptHaltException()
    {
        
        $med = $this->getMock('Artax\Mediator', array('notify'),
            array(new Provider(new ReflectionCache))
        );
        $stub = $this->getMock('Artax\Handler', NULL, array(1, $med));
        $med->expects($this->once())
             ->method('notify')
             ->will($this->throwException(new ScriptHaltException));
        
        // We have to push a listener otherwise notify won't be called
        $med->push('exception', function(){});
        $this->assertNull($stub->shutdown());
    }
    
    /**
     * @covers Artax\Handler::shutdown
     */
    public function testShutdownFallsBackToDefaultOnNotifyException()
    {
        
        $med = $this->getMock('Artax\Mediator', array('notify'),
            array(new Provider(new ReflectionCache))
        );
        $stub = $this->getMock('Artax\Handler',
            array('defaultHandlerMsg'), array(1, $med)
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
     * @covers Artax\Handler::exception
     */
    public function testExHandlerNotifiesShutdownEventOnFatalRuntimeError()
    {
        ob_start();
        
        $med = $this->getMock('Artax\Mediator', array('notify'),
            array(new Provider(new ReflectionCache))
        );
        $stub = $this->getMock('Artax\Handler',
            NULL, array(1, $med)
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
     * @covers Artax\Handler::exception
     */
    public function testExHandlerFallsBackToDefaultOnNotifyException()
    {
        
        $med = $this->getMock('Artax\Mediator', array('notify'),
            array(new Provider(new ReflectionCache))
        );
        $stub = $this->getMock('Artax\Handler',
            array('defaultHandlerMsg'), array(1, $med)
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
     * @covers Artax\Handler::exception
     */
    public function testExHandlerExitsOnNotifyScriptHaltException()
    {
        
        $med = $this->getMock('Artax\Mediator', array('notify'), 
            array(new Provider(new ReflectionCache))
        );
        $stub = $this->getMock('Artax\Handler',
            NULL, array(1, $med)
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
