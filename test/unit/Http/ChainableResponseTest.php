<?php

use Artax\Http\ChainableResponse;

class ChainableResponseTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\ChainableResponse::setPreviousResponse
     * @covers Artax\Http\ChainableResponse::resultedFromRedirection
     */
    public function testResultedFromRedirectionReturnsBooleanStatus() {
        $response = new ChainableResponse();
        $this->assertFalse($response->resultedFromRedirection());
        
        $response->setHeader('Location', 'http://localhost/redir-path');
        
        $redirection = new ChainableResponse();
        $redirection->setPreviousResponse($response);
        $this->assertTrue($redirection->resultedFromRedirection());
        
        return $redirection;
    }
    
    /**
     * @covers Artax\Http\ChainableResponse::getPreviousResponse
     * @expectedException Artax\Http\Exceptions\NotRedirectedException
     */
    public function testGetPreviousResponseThrowsExceptionIfNoPreviousResponseAssigned() {
        $response = new ChainableResponse();
        $response->getPreviousResponse();
    }
    
    /**
     * @depends testResultedFromRedirectionReturnsBooleanStatus
     * @covers Artax\Http\ChainableResponse::getPreviousResponse
     */
    public function testGetPreviousResponseReturnsPreviousResponseInTheChain($redirection) {
        $this->assertInstanceOf(
            'Artax\\Http\\ChainableResponse',
            $redirection->getPreviousResponse()
        );
    }
    
    /**
     * @depends testResultedFromRedirectionReturnsBooleanStatus
     * @covers Artax\Http\ChainableResponse::getRedirectionUri
     */
    public function testGetRedirectionUriReturnsLocationHeaderFromPreviousResponse($redirection) {
        $this->assertEquals('http://localhost/redir-path', $redirection->getRedirectionUri());
    }
    
    /**
     * @covers Artax\Http\ChainableResponse::getRedirectionUri
     * @expectedException Artax\Http\Exceptions\NotRedirectedException
     */
    public function testGetRedirectionUriThrowsExceptionIfNoPreviousResponseAssigned() {
        $response = new ChainableResponse();
        $response->getRedirectionUri();
    }
    
}