<?php

class DotNotationTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\DotNotation::parse
   */
  public function testParseParsesClassAndMethodWhenExpected()
  {
    $dn = new Artax\DotNotation;
    $dotStr = 'namespace.ClassName.methodName';
    $arr = $dn->parse($dotStr, FALSE, TRUE);
    
    $this->assertEquals('\namespace\ClassName', $arr[0]);
    $this->assertEquals('methodName', $arr[1]);
  }
  
  /**
   * @covers Artax\DotNotation::parse
   */
  public function testParseParsesClassOnlyWhenMethodParameterIsFalse()
  {
    $dn = new Artax\DotNotation;
    $dotStr = 'namespace.ClassName.methodName';
    $str = $dn->parse($dotStr);
    
    $this->assertEquals('\namespace\ClassName\methodName', $str);
  }
}























