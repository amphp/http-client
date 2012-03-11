<?php

class DotNotationTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Ioc\DotNotation::parse
   */
  public function testParseParsesClassAsExpected()
  {
    $dn = new Artax\Ioc\DotNotation;
    $dotStr = 'Namespace.ClassName';
    $this->assertEquals('\Namespace\ClassName', $dn->parse($dotStr));
  }
  
  /**
   * @covers Artax\Ioc\DotNotation::parse
   */
  public function testParseReverseOnTrueParameter()
  {
    $dn = new Artax\Ioc\DotNotation;
    $dotStr = 'Namespace\ClassName';    
    $this->assertEquals('Namespace.ClassName', $dn->parse($dotStr, TRUE));
  }
}
