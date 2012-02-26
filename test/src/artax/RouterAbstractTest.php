<?php

class RouterAbstractTest extends PHPUnit_Framework_TestCase
{
  public function setUp()
  {
    $_SERVER['HTTP_HOST'] = 'artax';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['QUERY_STRING'] = '';
    $_SERVER['REQUEST_URI'] = '/';
  }
  
  /**
   * @covers artax\RouterAbstract::__construct
   */
  public function testConstructorInitializesDependenciesOnInstantiation()
  {
    $dp = new artax\DepProvider(new artax\DotNotation);
    $rl = new artax\RouteList;
    $m  = new artax\Matcher;
    $md = new artax\Mediator;
    $r  = new artax\blocks\http\HttpRequest;
    $c  = new RouterAbstractTestClass($dp, $m, $md, $rl, $r);
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
  
  public function dispatch()
  {
    return TRUE;
  }
}
