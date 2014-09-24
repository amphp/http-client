<?php

namespace Amp\Test\Artax;

use Amp\Artax\Response;

class ResponseTest extends \PHPUnit_Framework_TestCase {
    public function testGetAndSetStatus() {
        $request = new Response;
        $request->setStatus(200);
        $this->assertEquals(200, $request->getStatus());
    }

    public function testGetAndSetReason() {
        $request = new Response;
        $request->setReason("I'M A LITTLE TEAPOT");
        $this->assertEquals("I'M A LITTLE TEAPOT", $request->getReason());
    }
}
