<?php

use Artax\ClientMultiResponse,
    Artax\Http\Response;

class ClientMultiResponseTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\ClientMultiResponse::__construct
     * @covers Artax\ClientMultiResponse::validateResponses
     */
    public function testBeginsEmpty() {
        $arr = array(
            $this->getMock('Artax\\Http\\Response'),
            $this->getMock('Exception')
        );
        $multiResponse = new ClientMultiResponse($arr);
        $this->assertInstanceOf('Artax\\ClientMultiResponse', $multiResponse);
    }
    
    /**
     * @covers Artax\ClientMultiResponse::__construct
     * @covers Artax\ClientMultiResponse::validateResponses
     * @expectedException Spl\TypeException
     */
    public function testConstructorThrowsExceptionOnEmptyResponseArray() {
        $arr = array();
        $multiResponse = new ClientMultiResponse($arr);
    }
    
    /**
     * @covers Artax\ClientMultiResponse::__construct
     * @covers Artax\ClientMultiResponse::validateResponses
     * @expectedException Spl\TypeException
     */
    public function testConstructorThrowsExceptionOnInvalidTypesWithinResponseArray() {
        $arr = array(new StdClass, 42);
        $multiResponse = new ClientMultiResponse($arr);
    }
    
    /**
     * @covers Artax\ClientMultiResponse::getErrorCount
     */
    public function testErrorCountGetterReturnsTheNumberOfExceptionObjectsInTheResponseArray() {
        $arr = array(
            $this->getMock('Artax\\Http\\Response'),
            $this->getMock('Exception'),
            $this->getMock('Exception'),
            $this->getMock('Exception')
        );
        $multiResponse = new ClientMultiResponse($arr);
        $this->assertEquals(3, $multiResponse->getErrorCount());
    }
    
    /**
     * @covers Artax\ClientMultiResponse::getAllErrors
     */
    public function testGetAllErrorsReturnsArrayOfErrorsInTheResponseArray() {
        $e1 = new Exception('e1');
        $e2 = new Exception('e2');
        $e3 = new Exception('e3');
        
        $arr = array(
            $e1,
            $e2,
            $e3,
            $this->getMock('Artax\\Http\\Response'),
        );
        $multiResponse = new ClientMultiResponse($arr);
        $this->assertEquals(array($e1, $e2, $e3), $multiResponse->getAllErrors());
    }
    
    /**
     * @covers Artax\ClientMultiResponse::getAllResponses
     */
    public function testGetAllResponsesReturnsArrayOfResponsesInTheOriginalArray() {
        $r1 = $this->getMock('Artax\\Http\\Response');
        $r2 = $this->getMock('Artax\\Http\\Response');
        $e1 = new Exception('e1');
        $e2 = new Exception('e2');
        $e3 = new Exception('e3');
        
        $arr = array(
            $r1,
            $r2,
            $e1,
            $e2,
            $e3
        );
        $multiResponse = new ClientMultiResponse($arr);
        $this->assertEquals(array($r1, $r2), $multiResponse->getAllResponses());
        
        return $multiResponse;
    }
    
    /**
     * @depends testGetAllResponsesReturnsArrayOfResponsesInTheOriginalArray
     * @covers Artax\ClientMultiResponse::count
     */
    public function testCountReturnsTotalNumberOfResponsesAndErrors($multiResponse) {
        $this->assertEquals(5, count($multiResponse));
    }
    
    /**
     * @depends testGetAllResponsesReturnsArrayOfResponsesInTheOriginalArray
     * @covers Artax\ClientMultiResponse::rewind
     * @covers Artax\ClientMultiResponse::current
     * @covers Artax\ClientMultiResponse::key
     * @covers Artax\ClientMultiResponse::next
     * @covers Artax\ClientMultiResponse::valid
     * @covers Artax\ClientMultiResponse::isError
     * @covers Artax\ClientMultiResponse::isResponse
     */
    public function testResponseIteration($multiResponse) {
        foreach ($multiResponse as $key => $value) {
            $this->assertEquals($value instanceof Response, $multiResponse->isResponse());
            $this->assertEquals($value instanceof Exception, $multiResponse->isError());
        }
    }
}



















