<?php

class DotNotationTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\DotNotation::parse
   */
  public function testParseParsesClassAndMethodWhenExpected()
  {
    $dn = new artax\DotNotation;
    $dotStr = 'namespace.ClassName.methodName';
    $arr = $dn->parse($dotStr, TRUE);
    
    $this->assertEquals('\namespace\ClassName', $arr[0]);
    $this->assertEquals('methodName', $arr[1]);
  }
  
  /**
   * @covers artax\DotNotation::parse
   */
  public function testParseParsesClassOnlyWhenMethodParameterIsFalse()
  {
    $dn = new artax\DotNotation;
    $dotStr = 'namespace.ClassName.methodName';
    $str = $dn->parse($dotStr, FALSE);
    
    $this->assertEquals('\namespace\ClassName\methodName', $str);
  }
  
  /**
   * @covers artax\DotNotation::parse
   */
  public function testParseThrowsExceptionOnInvalidString()
  {
    $invalid = [NULL, '...', '.'];
    $dn = new artax\DotNotation;
    foreach ($invalid as $dotStr) {
      try {
        $ex = FALSE;
        $dn->parse($dotStr);
      } catch (artax\exceptions\InvalidArgumentException $e) {
        $ex = TRUE;
      }
      $msg = "Failed to throw exception on invalid dot notation string: $dotStr";
      $this->assertTrue($ex, $msg);
    }
  }
}























