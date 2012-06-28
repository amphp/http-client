<?php

use Artax\Provider,
    Artax\FatalErrorException,
    Artax\Handler,
    Artax\ScriptHaltException,
    Artax\ReflectionCacher;

class HandlerTest extends PHPUnit_Framework_TestCase {

    /**
     * 
     */
    public function tearDown() {
        restore_error_handler();
        restore_exception_handler();
    }
    
    /**
     * @covers Artax\Handler::__construct
     */
    public function testBeginsEmpty() {
        $dp  = new Provider(new ReflectionCacher);
        $med = $this->getMock('Artax\Notifier', null,  array($dp));
        $obj = new Handler(1, $med);
    }
    
    /**
     * @covers Artax\Handler::register
     */
    public function testRegisterReturnsInstanceForChaining() {
        $dp  = new Provider(new ReflectionCacher);
        $med = $this->getMock('Artax\Notifier', null,  array($dp));
        $t = $this->getMock('Artax\Handler', array('exception',
            'shutdown', 'setNotifier', 'getFatalErrorException', 'lastError',
            'defaultHandlerMsg'
            ),
            array(0, $med)
        );
        
        $this->assertEquals($t->register(), $t);
    }
    
    /**
     * @covers Artax\Handler::error
     */
    public function testErrorNotifiesMediatorOnError() {
        ob_start();
        $msg = 'test notice message in testFile.php on line 42';
        $ex  = new ErrorException($msg, E_NOTICE);
        
        $dp  = new Provider(new ReflectionCacher);
        $med = $this->getMock('Artax\Notifier', array('notify'),  array($dp));
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
    public function testErrorChainsOnExceptionAndClearsBufferedOutput() {
        $dp  = new Provider(new ReflectionCacher);
        $med = $this->getMock('Artax\Notifier', array('notify'),  array($dp));
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
    public function testShutdownInvokesExceptionMethodOnFatalError() {
        $lastErr = array(
            'type'    => 1,
            'message' => 'The black knight always triumphs!',
            'file'    => '/path/to/file.php',
            'line'    => 42
        );
        
        $dp   = new Provider(new ReflectionCacher);
        $med  = $this->getMock('Artax\Notifier', null,  array($dp));
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
    public function testDefaultHandlerMsgReturnsExpectedString() {
        $dp  = new Provider(new ReflectionCacher);
        
        $med = $this->getMock('Artax\Notifier', null,  array($dp));
        
        $obj = new Handler(0, $med);
        
        ob_start();
        $obj->exception(new Exception('test'));
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals(null, $output);
        
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
    public function testLastErrorReturnsNullOnNoFatalPHPError() {
        $dp  = new Provider(new ReflectionCacher);
        
        $med = $this->getMock('Artax\Notifier', null,  array($dp));
        $obj = new Handler(1, $med);
        $this->assertEquals(null, $obj->getFatalErrorException());
    }
    
    /**
     * @covers Artax\Handler::shutdown
     */
    public function testShutdownNotifiesListenersIfNotifierExists() {
        
        $medStub = $this->getMock(
            'Artax\Notifier', array('all', 'keys'),
            array(new Provider(new ReflectionCacher))
        );
        $obj = $this->getMock('Artax\Handler',
            array('getFatalErrorException'), array(1, $medStub)
        );
        $obj->expects($this->once())
            ->method('getFatalErrorException')
            ->will($this->returnValue(null));      
        $obj->shutdown();
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExceptionMethodReturnsQuietlyOnPurposefulScriptHalt() {
        
        $med = $this->getMock('Artax\Notifier', array('notify'),
            array(new Provider(new ReflectionCacher))
        );
        $obj = new Handler(1, $med);
        $this->assertNull($obj->exception(new ScriptHaltException));
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExceptionMethodClearsBufferOnFatalException() {
        $med = $this->getMock('Artax\Notifier', array('notify'),
            array(new Provider(new ReflectionCacher))
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
    public function testExceptionMethodChangesErrorReportingOnFatalException() {
        $med = $this->getMock('Artax\Notifier', array('notify'),
            array(new Provider(new ReflectionCacher))
        );
        $obj = new Handler(1, $med);
        ob_start();
        $this->assertNull($obj->exception(new FatalErrorException));
        ob_end_clean();
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExceptionMethodOutputsDefaultMessageIfNoNotifierExists() {
        
        $med = $this->getMock('Artax\Notifier', array('notify'),
            array(new Provider(new ReflectionCacher))
        );
        $stub = $this->getMock('Artax\Handler',
            array('defaultHandlerMsg'), array(1, $med)
        );
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->with($this->equalTo(new Exception))
             ->will($this->returnValue(null));
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExceptionMethodNotifiesMediatorOnUncaughtException() {
        
        $mediator = new Artax\Notifier(new Provider(new ReflectionCacher));
        
        $stdObj = new StdClass;
        $mediator->push('exception', function(Exception $e, $debugLevel) use ($stdObj) {
            $stdObj->e = $e;
            $stdObj->debugLevel = $debugLevel;
        });
        
        
        $handler = new Artax\Handler(1, $mediator);
        $handler->exception(new Exception);
        
        $this->assertEquals(new Exception, $stdObj->e);
        $this->assertEquals(true, $stdObj->debugLevel);
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExceptionMethodFallsBackToDefaultMessageOnNotifyException() {
        
        $med = $this->getMock('Artax\Notifier', array('notify'),
            array(new Provider(new ReflectionCacher))
        );
        
        $med->push('exception', function(){});
        
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
             ->will($this->returnValue(null));
        
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExceptionMethodReturnsImmediatelyOnNotifyScriptHaltException() {
        $mediator = new Artax\Notifier(new Provider(new ReflectionCacher));
        $stub = $this->getMock('Artax\Handler', array('notify'), array(1, $mediator));
        $mediator->push('exception', function(){ throw new ScriptHaltException; });
        
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Handler::shutdown
     */
    public function testShutdownReturnsImmediatelyOnNotifyScriptHaltException() {
        
        $mediator = new Artax\Notifier(new Provider(new ReflectionCacher));
        $stub = $this->getMock('Artax\Handler', null, array(1, $mediator));
        $mediator->push('shutdown', function(){ throw new ScriptHaltException; });
        $this->assertNull($stub->shutdown());
    }
    
    /**
     * @covers Artax\Handler::shutdown
     */
    public function testShutdownFallsBackToDefaultOnNotifyException() {
        
        $mediator = new Artax\Notifier(new Provider(new ReflectionCacher));
        $stub = $this->getMock('Artax\Handler', array('defaultHandlerMsg'), array(1, $mediator));
        $mediator->push('shutdown', function(){ throw new Exception; });
        
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->will($this->returnValue('test exception output'));        
        
        $this->expectOutputString('test exception output');
        $stub->shutdown();
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExceptionMethodNotifiesShutdownEventOnFatalRuntimeError() {
        ob_start();
        
        $med = $this->getMock('Artax\Notifier', array('notify'),
            array(new Provider(new ReflectionCacher))
        );
        $stub = $this->getMock('Artax\Handler',
            null, array(1, $med)
        );
        
        // notify is also called on push/unshift
        $med->expects($this->exactly(4))
             ->method('notify')
             ->will($this->returnValue(null));
             
        // We have to push listeners otherwise notify won't be called
        $med->push('exception', function(){});
        $med->push('shutdown', function(){});
        $stub->exception(new FatalErrorException);
        ob_end_clean();
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExceptionMethodFallsBackToDefaultOnNotifyException() {
        
        $mediator = new Artax\Notifier(new Provider(new ReflectionCacher));
        $stub = $this->getMock('Artax\Handler', array('defaultHandlerMsg'), array(1, $mediator));
        $mediator->push('exception', function() {
            throw new Exception('test exception output');
        });
        
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->will($this->returnValue('test exception output'));
             
        $this->expectOutputString('test exception output');
        $stub->exception(new FatalErrorException);
    }
    
    /**
     * @covers Artax\Handler::exception
     */
    public function testExceptionMethodExitsQuietlyOnScriptHaltExceptionThrownByListeners() {
        
        $mediator = new Artax\Notifier(new Provider(new ReflectionCacher));
        $handler = new Artax\Handler(1, $mediator);
        
        $mediator->push('exception', function() { throw new ScriptHaltException; });
        
        $this->assertNull($handler->exception(new FatalErrorException));
    }
}
