<?php

class ErrorsTest extends PHPUnit_Framework_TestCase
{
    use UsesErrorExceptionsTrait;
    
    public function setUp()
    {
        $this->setUpErrorHandler();
    }
    
    public function tearDown()
    {
        $this->tearDownErrorHandler();
    }
    
    /**
     * @covers Artax\Core\Handlers\Errors::handle
     * @expectedException ErrorException
     */
    public function testHandleThrowsErrorException()
    {
        $obj = new Artax\Core\Handlers\Errors;
        $obj->handle(E_NOTICE, 'test notice message', 'testFile.php', 42);
    }
    
    /**
     * @covers Artax\Core\Handlers\Errors::__construct
     */
    public function testConstructorInitializesDebugProperty()
    {
        $obj = new ErrorsTestImplementation(FALSE);
        $this->assertFalse($obj->debug);
    }
    
    /**
     * @covers Artax\Core\Handlers\Errors::register
     */
    public function testRegisterReturnsObjectInstance()
    {
        $eh = $this->getMock('Artax\Core\Handlers\Errors', ['handle']);
        $this->assertEquals($eh->register(), $eh);
        
        $eh = $this->getMock('Artax\Core\Handlers\Errors', ['handle'], [FALSE]);
        $this->assertEquals($eh->register(), $eh);
    }
    
    /**
     * @covers Artax\Core\Handlers\Errors::handle
     */
    public function testHandlerReturnsExpectedMessage()
    {
        $msg     = 'Notice: test notice message in testFile.php on line 42';
        $exMsg = '';
        $obj     = new Artax\Core\Handlers\Errors;
        try {
            $obj->handle(E_NOTICE, 'test notice message', 'testFile.php', 42);
        } catch (ErrorException $e) {
            $exMsg = $e->getMessage();
        }
        $this->assertEquals($msg, $exMsg);
    }
}

class ErrorsTestImplementation extends Artax\Core\Handlers\Errors
{
    use MagicTestGetTrait;
}
