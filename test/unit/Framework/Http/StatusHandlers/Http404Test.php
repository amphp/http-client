<?php

use Artax\Framework\Http\StatusHandlers\Http404,
    Artax\Framework\Http\Exceptions\NotFoundException;

class Http404Test extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Http\StatusHandlers\Http404::__construct
     */
    public function testBeginsEmpty() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $request  = $this->getMock('Artax\\Http\\Request');
        $response = $this->getMock('Artax\\Http\\Response');
        
        $handler = new Http404($mediator, $request, $response);
        $this->assertInstanceOf(
            'Artax\\Framework\\Http\\StatusHandlers\\Http404',
            $handler
        );
    }
    
    /**
     * @covers Artax\Framework\Http\StatusHandlers\Http404::__invoke
     */
    public function testHandlerNotifiesListenerAndSendsResponse() {
        $e = new NotFoundException;
        
        $request  = $this->getMock('Artax\\Http\\Request');
        $response = $this->getMock('Artax\\Http\\Response');
        $response->expects($this->once())
                 ->method('setStatusCode')
                 ->with(404);
        $response->expects($this->once())
                 ->method('setStatusDescription')
                 ->with('Not Found');
        $response->expects($this->once())
                 ->method('wasSent')
                 ->will($this->returnValue(false));
        $response->expects($this->once())
                 ->method('send');
        
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('app.http-404', $request, $response, $e)
                 ->will($this->returnValue(1));
        
        $handler = new Http404($mediator, $request, $response);
        $handler($e);
    }
    
    /**
     * @covers Artax\Framework\Http\StatusHandlers\Http404::__invoke
     */
    public function testHandlerSendsDefaultResponseIfNoUserListenersRegistered() {
        $e = new NotFoundException;
        
        $request  = $this->getMock('Artax\\Http\\Request');
        $response = $this->getMock('Artax\\Http\\Response');
        
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('app.http-404', $request, $response, $e)
                 ->will($this->returnValue(0));
        
        $handler = new Http404($mediator, $request, $response);
        $handler($e);
    }
    
}
