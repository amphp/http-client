<?php

namespace Amp\Test\Artax;

use Amp\Artax\Request;

class RequestTest extends \PHPUnit_Framework_TestCase {
    public function testGetAndSetMethod() {
        $request = new Request;
        $request->setMethod('GET');
        $this->assertEquals('GET', $request->getMethod());
    }

    public function testGetAndSetUri() {
        $request = new Request;
        $request->setUri('http://www.google.com');
        $this->assertEquals('http://www.google.com', $request->getUri());
    }
}
