<?php

use Artax\Framework\Http\StatusHandlers\Http500;;

class Http500Test extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Http\StatusHandlers\Http500::__construct
     */
    public function testBeginsEmpty() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $request  = $this->getMock('Artax\\Http\\Request');
        $response = $this->getMock('Artax\\Http\\MutableResponse');
        
        $handler = new Http500($mediator, $request, $response);
        $this->assertInstanceOf(
            'Artax\\Framework\\Http\\StatusHandlers\\Http500',
            $handler
        );
    }
    
    /**
     * @covers Artax\Framework\Http\StatusHandlers\Http500::__invoke
     */
    public function testHandlerNotifiesListenerAndSendsResponse() {
        $e = new Exception('message');
        
        $request  = $this->getMock('Artax\\Http\\Request');
        $response = $this->getMock('Artax\\Http\\MutableResponse');
        $response->expects($this->once())
                 ->method('setStatusCode')
                 ->with(500);
        $response->expects($this->once())
                 ->method('setStatusDescription')
                 ->with('Internal Server Error');
        $response->expects($this->once())
                 ->method('wasSent')
                 ->will($this->returnValue(false));
        $response->expects($this->once())
                 ->method('send');
        
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('app.http-500', $request, $response, $e)
                 ->will($this->returnValue(1));
        
        $handler = new Http500($mediator, $request, $response);
        $handler($e, 1);
    }
    
    /**
     * @covers Artax\Framework\Http\StatusHandlers\Http500::__invoke
     */
    public function testHandlerSendsDefaultResponseIfNoUserListenersRegistered() {
        $e = new Exception('message');
        
        $request  = $this->getMock('Artax\\Http\\Request');
        $response = $this->getMock('Artax\\Http\\MutableResponse');
        
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->exactly(2))
                 ->method('notify')
                 ->with('app.http-500', $request, $response, $e)
                 ->will($this->returnValue(0));
        
        $handler = new Http500($mediator, $request, $response);
        $handler($e, 0);
        $handler($e, 1);
    }
    
}
