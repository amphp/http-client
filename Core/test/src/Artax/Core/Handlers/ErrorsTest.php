<?php

class ErrorsTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\Core\Handlers\Errors::__construct
     */
    public function testConstructorInitializesDependencies()
    {
        $dp  = new Artax\Core\Ioc\Provider;
        $med = $this->getMock('Artax\Core\Events\Mediator', NULL, [$dp]);
        $obj = new ErrorsTestImplementation($med, TRUE);
        $this->assertTrue($obj->debug);
        $this->assertEquals($med, $obj->mediator);
    }
    
    /**
     * @covers Artax\Core\Handlers\Errors::register
     */
    public function testRegisterReturnsObjectInstance()
    {
        $dp  = new Artax\Core\Ioc\Provider;
        $med = $this->getMock('Artax\Core\Events\Mediator', NULL, [$dp]);
        
        $eh = $this->getMock('Artax\Core\Handlers\Errors', ['handle'], [$med, FALSE]);
        $this->assertEquals($eh->register(), $eh);
        
        $eh = $this->getMock('Artax\Core\Handlers\Errors', ['handle'], [$med, TRUE]);
        $this->assertEquals($eh->register(), $eh);
    }
    
    /**
     * @covers Artax\Core\Handlers\Errors::handle
     */
    public function testHandlerNotifiesMediatorOnError()
    {
        $msg = 'Notice: test notice message in testFile.php on line 42';
        $ex = new ErrorException($msg, E_NOTICE);
        
        $dp  = new Artax\Core\Ioc\Provider;
        $med = $this->getMock('Artax\Core\Events\Mediator', ['notify'], [$dp]);
        $med->expects($this->once())
            ->method('notify')
            ->with('error', $ex, TRUE);
        
        $eh = new Artax\Core\Handlers\Errors($med, TRUE);
        $eh->handle(E_NOTICE, 'test notice message', 'testFile.php', 42);
    }
}

class ErrorsTestImplementation extends Artax\Core\Handlers\Errors
{
    use MagicTestGetTrait;
}
