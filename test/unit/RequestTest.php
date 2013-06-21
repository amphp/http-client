<?php

use Artax\Request;

class RequestTest extends PHPUnit_Framework_TestCase {
    
    function testGetAndSetMethod() {
        $request = new Request;
        $request->setMethod('GET');
        $this->assertEquals('GET', $request->getMethod());
    }
    
    function testGetAndSetUri() {
        $request = new Request;
        $request->setUri('http://www.google.com');
        $this->assertEquals('http://www.google.com', $request->getUri());
    }
    
}

