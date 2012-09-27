<?php

use Artax\MultiResponse,
    Artax\Http\Response;

class MultiResponseTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\MultiResponse::__construct
     * @covers Artax\MultiResponse::validateResponses
     */
    public function testBeginsEmpty() {
        $arr = array(
            $this->getMock('Artax\\Http\\Response'),
            $this->getMock('Exception')
        );
        $multiResponse = new MultiResponse($arr);
        $this->assertInstanceOf('Artax\\MultiResponse', $multiResponse);
    }
    
    /**
     * @covers Artax\MultiResponse::__construct
     * @covers Artax\MultiResponse::validateResponses
     * @expectedException Spl\ValueException
     */
    public function testConstructorThrowsExceptionOnEmptyResponseArray() {
        $arr = array();
        $multiResponse = new MultiResponse($arr);
    }
    
    /**
     * @covers Artax\MultiResponse::__construct
     * @covers Artax\MultiResponse::validateResponses
     * @expectedException Spl\ValueException
     */
    public function testConstructorThrowsExceptionOnInvalidTypesWithinResponseArray() {
        $arr = array(new StdClass, 42);
        $multiResponse = new MultiResponse($arr);
    }
    
    /**
     * @covers Artax\MultiResponse::getErrorCount
     */
    public function testErrorCountGetterReturnsTheNumberOfExceptionObjectsInTheResponseArray() {
        $arr = array(
            $this->getMock('Artax\\Http\\Response'),
            $this->getMock('Exception'),
            $this->getMock('Exception'),
            $this->getMock('Exception')
        );
        $multiResponse = new MultiResponse($arr);
        $this->assertEquals(3, $multiResponse->getErrorCount());
    }
    
    /**
     * @covers Artax\MultiResponse::getAllErrors
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
        $multiResponse = new MultiResponse($arr);
        $this->assertEquals(array($e1, $e2, $e3), $multiResponse->getAllErrors());
    }
    
    /**
     * @covers Artax\MultiResponse::getAllResponses
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
        $multiResponse = new MultiResponse($arr);
        $this->assertEquals(array($r1, $r2), $multiResponse->getAllResponses());
        
        return $multiResponse;
    }
    
    /**
     * @depends testGetAllResponsesReturnsArrayOfResponsesInTheOriginalArray
     * @covers Artax\MultiResponse::count
     */
    public function testCountReturnsTotalNumberOfResponsesAndErrors($multiResponse) {
        $this->assertEquals(5, count($multiResponse));
    }
    
    /**
     * @depends testGetAllResponsesReturnsArrayOfResponsesInTheOriginalArray
     * @covers Artax\MultiResponse::rewind
     * @covers Artax\MultiResponse::current
     * @covers Artax\MultiResponse::key
     * @covers Artax\MultiResponse::next
     * @covers Artax\MultiResponse::valid
     * @covers Artax\MultiResponse::isError
     * @covers Artax\MultiResponse::isResponse
     */
    public function testResponseIteration($multiResponse) {
        foreach ($multiResponse as $key => $value) {
            $this->assertEquals($value instanceof Response, $multiResponse->isResponse());
            $this->assertEquals($value instanceof Exception, $multiResponse->isError());
        }
    }
}



















