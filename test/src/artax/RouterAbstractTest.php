<?php

class RouterAbstractTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\RouterAbstract::__construct
   */
  public function testConstructorInitializesDependenciesOnInstantiation()
  {
    $dp = new artax\DepProvider(new artax\DotNotation);
    $m  = new artax\Matcher(new artax\RouteList);
    $c  = new RouterAbstractTestClass($dp, $m);
    $this->assertEquals($dp, $c->getDeps());
    $this->assertEquals($m, $c->getMatcher());
  }
  
}

class RouterAbstractTestClass extends artax\RouterAbstract
{
  public function getMatcher()
  {
    return $this->matcher;
  }
  
  public function getDeps()
  {
    return $this->deps;
  }
  
  public function dispatch(artax\RequestInterface $request)
  {
    return TRUE;
  }
}
