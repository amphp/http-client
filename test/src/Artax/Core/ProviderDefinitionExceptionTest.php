<?php

class ProviderDefinitionExceptionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\Core\ProviderDefinitionException
     */
    public function testProviderDefinitionExceptionIsLogicException()
    {
      $e = new Artax\Core\ProviderDefinitionException();
      $this->assertInstanceOf('LogicException', $e);
    }
}
