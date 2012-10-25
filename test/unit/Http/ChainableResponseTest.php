<?php

use Artax\Http\ChainableResponse;

class ChainableResponseTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\ChainableResponse::__construct
     * @covers Artax\Http\ChainableResponse::setPreviousResponse
     * @covers Artax\Http\ChainableResponse::hasPreviousResponse
     */
    public function testHasPreviousResponseReturnsBooleanStatus() {
        $response = new ChainableResponse('http://someurl.com');
        $this->assertFalse($response->hasPreviousResponse());
        
        $response->setHeader('Location', 'http://localhost/redir-path');
        
        $redirection = new ChainableResponse($response->getHeader('Location'));
        $redirection->setPreviousResponse($response);
        $this->assertTrue($redirection->hasPreviousResponse());
        
        return $redirection;
    }
    
    /**
     * @depends testHasPreviousResponseReturnsBooleanStatus
     * @covers Artax\Http\ChainableResponse::getPreviousResponse
     */
    public function testGetPreviousResponseReturnsPreviousResponseInTheChain($redirection) {
        $this->assertInstanceOf(
            'Artax\\Http\\ChainableResponse',
            $redirection->getPreviousResponse()
        );
    }
    
    /**
     * @covers Artax\Http\ChainableResponse::__construct
     * @covers Artax\Http\ChainableResponse::getPreviousResponse
     * @expectedException LogicException
     */
    public function testGetPreviousResponseThrowsExceptionIfNoPreviousResponseAssigned() {
        $response = new ChainableResponse('http://someurl.com');
        $response->getPreviousResponse();
    }
    
    /**
     * @depends testHasPreviousResponseReturnsBooleanStatus
     * @covers Artax\Http\ChainableResponse::getRequestUri
     */
    public function testGetRequestUriReturnsLocationHeaderFromPreviousResponse($redirection) {
        $this->assertEquals('http://localhost/redir-path', $redirection->getRequestUri());
    }
    
    /**
     * @covers Artax\Http\ChainableResponse::getRequestUri
     */
    public function testGetRequestUri() {
        $response = new ChainableResponse('http://someurl.com');
        $this->assertEquals('http://someurl.com', $response->getRequestUri());
    }
    
}