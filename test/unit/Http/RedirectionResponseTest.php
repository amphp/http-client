<?php

use Artax\Http\RedirectionResponse;

class RedirectionResponseTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\RedirectionResponse::setPreviousResponse
     * @covers Artax\Http\RedirectionResponse::resultedFromRedirection
     */
    public function testResultedFromRedirectionReturnsBooleanStatus() {
        $response = new RedirectionResponse();
        $this->assertFalse($response->resultedFromRedirection());
        
        $response->setHeader('Location', 'http://localhost/redir-path');
        
        $redirection = new RedirectionResponse();
        $redirection->setPreviousResponse($response);
        $this->assertTrue($redirection->resultedFromRedirection());
        
        return $redirection;
    }
    
    /**
     * @covers Artax\Http\RedirectionResponse::getPreviousResponse
     * @expectedException Artax\Http\Exceptions\NotRedirectedException
     */
    public function testGetPreviousResponseThrowsExceptionIfNoPreviousResponseAssigned() {
        $response = new RedirectionResponse();
        $response->getPreviousResponse();
    }
    
    /**
     * @depends testResultedFromRedirectionReturnsBooleanStatus
     * @covers Artax\Http\RedirectionResponse::getPreviousResponse
     */
    public function testGetPreviousResponseReturnsPreviousResponseInTheChain($redirection) {
        $this->assertInstanceOf(
            'Artax\\Http\\RedirectionResponse',
            $redirection->getPreviousResponse()
        );
    }
    
    /**
     * @depends testResultedFromRedirectionReturnsBooleanStatus
     * @covers Artax\Http\RedirectionResponse::getRedirectionUri
     */
    public function testGetRedirectionUriReturnsLocationHeaderFromPreviousResponse($redirection) {
        $this->assertEquals('http://localhost/redir-path', $redirection->getRedirectionUri());
    }
    
    /**
     * @covers Artax\Http\RedirectionResponse::getRedirectionUri
     * @expectedException Artax\Http\Exceptions\NotRedirectedException
     */
    public function testGetRedirectionUriThrowsExceptionIfNoPreviousResponseAssigned() {
        $response = new RedirectionResponse();
        $response->getRedirectionUri();
    }
    
}