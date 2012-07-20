<?php

use Artax\Http\StatusCodes;

/**
 * @covers Artax\Http\StatusCodes
 */
class StatusCodesTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Http\StatusCodes
     */
    public function testInitialization() {
        $statusCodes = new StatusCodes();
        $this->assertInstanceOf('Artax\\Http\\StatusCodes', $statusCodes);
        $this->assertEquals(200, StatusCodes::HTTP_OK);
    }
    
}
