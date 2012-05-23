<?php

class ProviderDefinitionExceptionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\ProviderDefinitionException
     */
    public function testProviderDefinitionExceptionIsLogicException()
    {
      $e = new Artax\ProviderDefinitionException();
      $this->assertInstanceOf('LogicException', $e);
    }
}
