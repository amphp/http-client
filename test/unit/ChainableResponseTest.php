<?php

use Artax\ChainableResponse;

class ChainableResponseTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\ChainableResponse::__construct
     * @covers Artax\ChainableResponse::setPreviousResponse
     * @covers Artax\ChainableResponse::hasPreviousResponse
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
     * @covers Artax\ChainableResponse::getPreviousResponse
     */
    public function testGetPreviousResponseReturnsPreviousResponseInTheChain($redirection) {
        $this->assertInstanceOf(
            'Artax\\ChainableResponse',
            $redirection->getPreviousResponse()
        );
    }
    
    /**
     * @covers Artax\ChainableResponse::__construct
     * @covers Artax\ChainableResponse::getPreviousResponse
     * @expectedException LogicException
     */
    public function testGetPreviousResponseThrowsExceptionIfNoPreviousResponseAssigned() {
        $response = new ChainableResponse('http://someurl.com');
        $response->getPreviousResponse();
    }
    
    /**
     * @depends testHasPreviousResponseReturnsBooleanStatus
     * @covers Artax\ChainableResponse::getRequestUri
     */
    public function testGetRequestUriReturnsLocationHeaderFromPreviousResponse($redirection) {
        $this->assertEquals('http://localhost/redir-path', $redirection->getRequestUri());
    }
    
    /**
     * @covers Artax\ChainableResponse::getRequestUri
     */
    public function testGetRequestUri() {
        $response = new ChainableResponse('http://someurl.com');
        $this->assertEquals('http://someurl.com', $response->getRequestUri());
    }
    
}