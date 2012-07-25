<?php

use Artax\Framework\ScriptHaltException,
    Artax\Framework\FatalErrorException,
    Artax\Framework\UnifiedErrorHandler;

class UnifiedErrorHandlerTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\UnifiedErrorHandler::__construct
     * @covers Artax\Framework\UnifiedErrorHandler::handleError
     */
    public function testErrorHandlerThrowsAndShutsDownIfNoUserListenersExistToHandleIt() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $response = $this->getMock('Artax\\Http\\Response');
        $debugMode = 1;
        
        $mediator->expects($this->once())
                 ->method('notify')
                 ->will($this->returnValue(0));
        
        $mock = $this->getMock('Artax\\Framework\\UnifiedErrorHandler',
            array('handleException', 'handleShutdown'), array($response, $mediator, $debugMode)
        );
        $mock->expects($this->once())
             ->method('handleException');
        $mock->expects($this->once())
             ->method('handleShutdown');
        
        $scriptHaltExceptionThrown = false;
        
        try {
            $mock->handleError(E_WARNING, 'TEST WARNING', '/var/test/file', 42);
        } catch (ScriptHaltException $e) {
            $scriptHaltExceptionThrown = true;
        }
        
        $this->assertTrue($scriptHaltExceptionThrown);
    }
    
    /**
     * @covers Artax\Framework\UnifiedErrorHandler::__construct
     * @covers Artax\Framework\UnifiedErrorHandler::handleError
     */
    public function testErrorHandlerNotifiesListenersIfAvailable() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $response = $this->getMock('Artax\\Http\\Response');
        $debugMode = 1;
        
        $e = new ErrorException('test error in /path on line 42', E_WARNING);
        
        $mediator->expects($this->any())
                 ->method('notify')
                 ->with('__sys.error', $e, $debugMode)
                 ->will($this->returnValue(1));
        
        $handler = new UnifiedErrorHandler($response, $mediator, $debugMode);
        $this->assertNull($handler->handleError(E_WARNING, 'test error', '/path', 42));
        
    }
    
    /**
     * @covers Artax\Framework\UnifiedErrorHandler::__construct
     * @covers Artax\Framework\UnifiedErrorHandler::handleException
     */
    public function testExceptionHandlerIgnoresScriptHaltException() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $response = $this->getMock('Artax\\Http\\Response');
        $debugMode = 1;
        
        $handler = new UnifiedErrorHandler($response, $mediator, $debugMode);
        $this->assertNull($handler->handleException(new ScriptHaltException));
    }
    
    /**
     * @covers Artax\Framework\UnifiedErrorHandler::__construct
     * @covers Artax\Framework\UnifiedErrorHandler::handleException
     */
    public function testExceptionHandlerNotifiesListenersOnExceptionEvent() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('__sys.exception', new Exception, 1)
                 ->will($this->returnValue(1));
                 
        $response = $this->getMock('Artax\\Http\\Response');
        $debugMode = 1;
        
        $handler = new UnifiedErrorHandler($response, $mediator, $debugMode);
        $this->assertNull($handler->handleException(new Exception));
    }
    
    /**
     * @covers Artax\Framework\UnifiedErrorHandler::__construct
     * @covers Artax\Framework\UnifiedErrorHandler::handleException
     */
    public function testExceptionHandlerOutputsDefaultExceptionMessageIfNoListenersSpecified() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('__sys.exception', new Exception, 1)
                 ->will($this->returnValue(0));
                 
        $response = $this->getMock('Artax\\Http\\Response');
        $debugMode = 1;
        
        $mock = $this->getMock('Artax\\Framework\\UnifiedErrorHandler',
            array('outputDefaultExceptionMessage'), array($response, $mediator, $debugMode)
        );
        
        $mock->expects($this->once())
             ->method('outputDefaultExceptionMessage')
             ->with(new Exception);
        
        $this->assertNull($mock->handleException(new Exception));
    }
    
    /**
     * @covers Artax\Framework\UnifiedErrorHandler::__construct
     * @covers Artax\Framework\UnifiedErrorHandler::handleException
     */
    public function testExceptionHandlerReturnsWhenListenerThrowsScriptHaltException() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('__sys.exception', new Exception, 1)
                 ->will($this->throwException(new ScriptHaltException));
                 
        $response = $this->getMock('Artax\\Http\\Response');
        $debugMode = 1;
        
        $handler = new UnifiedErrorHandler($response, $mediator, $debugMode);
        
        $this->assertNull($handler->handleException(new Exception));
    }
    
    /**
     * @covers Artax\Framework\UnifiedErrorHandler::__construct
     * @covers Artax\Framework\UnifiedErrorHandler::handleException
     * @covers Artax\Framework\UnifiedErrorHandler::outputDefaultExceptionMessage
     */
    public function testExceptionHandlerOutputsDefaultExceptionMessageIfListenersThrowException() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('__sys.exception', new Exception, 1)
                 ->will($this->throwException(new Exception));
                 
        $response = $this->getMock('Artax\\Http\\Response');
        $debugMode = 1;
        
        $handler = new UnifiedErrorHandler($response, $mediator, $debugMode);
        
        $this->assertNull($handler->handleException(new Exception));
    }
    
    /**
     * @covers Artax\Framework\UnifiedErrorHandler::__construct
     * @covers Artax\Framework\UnifiedErrorHandler::handleException
     * @covers Artax\Framework\UnifiedErrorHandler::outputDefaultExceptionMessage
     */
    public function testDefaultMessageOnlyOutputsOnce() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('__sys.exception', new Exception, 1)
                 ->will($this->throwException(new Exception));
                 
        $response = $this->getMock('Artax\\Http\\Response');
        $response->expects($this->once())
                 ->method('wasSent')
                 ->will($this->returnValue(true));
        
        $debugMode = 1;
        
        $handler = new UnifiedErrorHandler($response, $mediator, $debugMode);
        
        $this->assertNull($handler->handleException(new Exception));
    }
    
    /**
     * @runInSeparateProcess
     * @covers Artax\Framework\UnifiedErrorHandler::register
     */
    public function testRegisterReturnsNull() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $response = $this->getMock('Artax\\Http\\Response');
        $debugMode = 1;
        
        $handler = new UnifiedErrorHandler($response, $mediator, $debugMode);
        $this->assertNull($handler->register());
    }
    
    /**
     * @covers Artax\Framework\UnifiedErrorHandler::handleShutdown
     * @covers Artax\Framework\UnifiedErrorHandler::buildExceptionFromFatalError
     * @covers Artax\Framework\UnifiedErrorHandler::getLastError
     */
    public function testHandleShutdownReturnsIfPreviouslyInvoked() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('__sys.shutdown');
                 
        $response = $this->getMock('Artax\\Http\\Response');
        $debugMode = 1;
        
        $handler = $this->getMock(
            'Artax\\Framework\\UnifiedErrorHandler',
            null,
            array($response, $mediator, $debugMode)
        );
        
        $handler->handleShutdown();
        $this->assertNull($handler->handleShutdown());
    }
    
    /**
     * @covers Artax\Framework\UnifiedErrorHandler::handleShutdown
     */
    public function testHandleShutdownReturnsIfShutdownListenerThrowsScriptHaltException() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('__sys.shutdown')
                 ->will($this->throwException(new ScriptHaltException));
                 
        $response = $this->getMock('Artax\\Http\\Response');
        $debugMode = 1;
        
        $handler = $this->getMock(
            'Artax\\Framework\\UnifiedErrorHandler',
            array('buildExceptionFromFatalError'),
            array($response, $mediator, $debugMode)
        );
        
        $handler->handleShutdown();
    }
    
    /**
     * @covers Artax\Framework\UnifiedErrorHandler::handleShutdown
     */
    public function testHandleShutdownOutputsDefaultOnUncaughtShutdownListenerException() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('__sys.shutdown')
                 ->will($this->throwException(new Exception));
                 
        $response = $this->getMock('Artax\\Http\\Response');
        $debugMode = 1;
        
        $handler = $this->getMock(
            'Artax\\Framework\\UnifiedErrorHandler',
            array('buildExceptionFromFatalError', 'outputDefaultExceptionMessage'),
            array($response, $mediator, $debugMode)
        );
        $handler->expects($this->once())
                ->method('outputDefaultExceptionMessage');
        
        $handler->handleShutdown();
    }
    
    /**
     * @covers Artax\Framework\UnifiedErrorHandler::handleShutdown
     * @covers Artax\Framework\UnifiedErrorHandler::buildExceptionFromFatalError
     */
    public function testHandleShutdownInvokesExceptionHandlerForFatalErrors() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $response = $this->getMock('Artax\\Http\\Response');
        $debugMode = 1;
        
        $handler = $this->getMock(
            'Artax\\Framework\\UnifiedErrorHandler',
            array('getLastError', 'handleException'),
            array($response, $mediator, $debugMode)
        );
        
        $lastError = array(
            'type'    => 1,
            'message' => 'The black knight always triumphs!',
            'file'    => '/path/to/file.php',
            'line'    => 42
        );
        
        $handler->expects($this->once())
                ->method('getLastError')
                ->will($this->returnValue($lastError));
        $handler->expects($this->once())
                ->method('handleException');
        
        $handler->handleShutdown();
    }
}























