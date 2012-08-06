<?php

use Artax\Framework\Http\StatusHandlers\HttpGeneral,
    Artax\Framework\Http\Exceptions\HttpStatusException;

class HttpGeneralTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Http\StatusHandlers\HttpGeneral::__construct
     */
    public function testBeginsEmpty() {
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $request  = $this->getMock('Artax\\Http\\Request');
        $response = $this->getMock('Artax\\Http\\MutableResponse');
        
        $handler = new HttpGeneral($mediator, $request, $response);
        $this->assertInstanceOf(
            'Artax\\Framework\\Http\\StatusHandlers\\HttpGeneral',
            $handler
        );
    }
    
    /**
     * @covers Artax\Framework\Http\StatusHandlers\HttpGeneral::__invoke
     */
    public function testHandlerNotifiesListenerAndSendsResponse() {
        $e = new HttpStatusException('message', 499);
        
        $request  = $this->getMock('Artax\\Http\\Request');
        $response = $this->getMock('Artax\\Http\\MutableResponse');
        $response->expects($this->once())
                 ->method('setStatusCode')
                 ->with(499);
        $response->expects($this->once())
                 ->method('setStatusDescription')
                 ->with('message');
        $response->expects($this->once())
                 ->method('wasSent')
                 ->will($this->returnValue(false));
        $response->expects($this->once())
                 ->method('send');
        
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('app.http-499', $request, $response, $e)
                 ->will($this->returnValue(1));
        
        $handler = new HttpGeneral($mediator, $request, $response);
        $handler($e);
    }
    
    /**
     * @covers Artax\Framework\Http\StatusHandlers\HttpGeneral::__invoke
     * @expectedException LogicException
     */
    public function testHandlerThrowsExceptionIfNoUserListenersRegisteredForSpecifiedCode() {
        $e = new HttpStatusException('message', 499);
        
        $request  = $this->getMock('Artax\\Http\\Request');
        $response = $this->getMock('Artax\\Http\\MutableResponse');
        
        $mediator = $this->getMock('Artax\\Events\\Mediator');
        $mediator->expects($this->once())
                 ->method('notify')
                 ->with('app.http-499', $request, $response, $e)
                 ->will($this->returnValue(0));
        
        $handler = new HttpGeneral($mediator, $request, $response);
        $handler($e);
    }
    
}
