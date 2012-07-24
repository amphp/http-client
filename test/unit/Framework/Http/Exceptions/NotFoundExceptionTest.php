<?php

use Artax\Framework\Http\Exceptions\NotFoundException;

/**
 * @covers Artax\Framework\Http\Exceptions\NotFoundException
 */
class NotFoundExceptionTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Http\Exceptions\NotFoundException::__construct
     */
    public function testNotFoundExceptionExtendsHttpStatusException() {
        try {
            throw new NotFoundException();
        } catch (NotFoundException $e) {
            $this->assertInstanceOf('Artax\\Framework\\Http\\Exceptions\\HttpStatusException', $e);
            $this->assertEquals('Not Found', $e->getMessage());
            $this->assertEquals(404, $e->getCode());
        }
    }
}
