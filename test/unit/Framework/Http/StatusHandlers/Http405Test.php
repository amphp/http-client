<?php

use Artax\Framework\Http\StatusHandlers\Http405,
    Artax\Framework\Http\Exceptions\MethodNotAllowedException;

class Http405Test extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Http\StatusHandlers\Http405::__construct
     */
    public function testBeginsEmpty() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $request  = $this->getMock('Artax\\Http\\Request');
        $response = $this->getMock('Artax\\Http\\MutableResponse');
        
        $handler = new Http405($mediator, $request, $response);
        $this->assertInstanceOf(
            'Artax\\Framework\\Http\\StatusHandlers\\Http405',
            $handler
        );
    }
    
    /**
     * @covers Artax\Framework\Http\StatusHandlers\Http405::__invoke
     */
    public function testHandlerNotifiesListenerAndSendsResponse() {
        $e = new MethodNotAllowedException(array('post'));
        
        $request  = $this->getMock('Artax\\Http\\Request');
        $response = $this->getMock('Artax\\Http\\MutableResponse');
        $response->expects($this->once())
                 ->method('setStatusCode')
                 ->with(405);
        $response->expects($this->once())
                 ->method('setStatusDescription')
                 ->with('Method Not Allowed');
        $response->expects($this->once())
                 ->method('setHeader')
                 ->with('Allow', 'POST');
        $response->expects($this->once())
                 ->method('wasSent')
                 ->will($this->returnValue(false));
        $response->expects($this->once())
                 ->method('send');
        
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('app.http-405', $request, $response, $e)
                 ->will($this->returnValue(1));
        
        $handler = new Http405($mediator, $request, $response);
        $handler($e);
    }
    
    /**
     * @covers Artax\Framework\Http\StatusHandlers\Http405::__invoke
     */
    public function testHandlerSendsDefaultResponseIfNoUserListenersRegistered() {
        $e = new MethodNotAllowedException(array('post'));
        
        $request  = $this->getMock('Artax\\Http\\Request');
        $response = $this->getMock('Artax\\Http\\MutableResponse');
        
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('app.http-405', $request, $response, $e)
                 ->will($this->returnValue(0));
        
        $handler = new Http405($mediator, $request, $response);
        $handler($e);
    }
    
}
