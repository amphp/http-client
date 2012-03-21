<?php

class PcntlInterruptTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\Core\Handlers\PcntlInterrupt::__construct
     */
    public function testBeginsEmpty()
    {
        $sigs = [
            SIGTERM => 'SIGTERM',
            SIGHUP  => 'SIGHUP',
            SIGINT  => 'SIGINT',
            SIGQUIT => 'SIGQUIT'
        ];
        $obj = new PcntlInterruptImplementationClass;
        $this->assertEquals($sigs, $obj->signals);
    }
    
    /**
     * @covers Artax\Core\Handlers\PcntlInterrupt::register
     */
    public function testRegisterAssignsSignalHandlers()
    {
        $obj = new PcntlInterruptImplementationClass;
        $obj->register();
    }
    
    /**
     * @covers Artax\Core\Handlers\PcntlInterrupt::handle
     * @expectedException Artax\Core\Handlers\PcntlInterruptException
     */
    public function testHandleThrowsExceptionWhenCalled()
    {
        $obj = new PcntlInterruptImplementationClass;
        $obj->handle(SIGINT);
    }
}

class PcntlInterruptImplementationClass extends Artax\Core\Handlers\PcntlInterrupt
{
    use MagicTestGetTrait;
}
