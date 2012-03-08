<?php

class RouteTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers Artax\Routing\Route::getConstraints
   * @covers Artax\Routing\Route::__construct
   */
  public function testGetConstraintsReturnsConstraintsProperty()
  {
    $r = new Artax\Routing\Route('/route', 'Controller');
    $this->assertEquals([], $r->getConstraints());
    
    $r = new Artax\Routing\Route('/route', 'Controller', ['_method'=>'GET']);
    $this->assertEquals(['_method'=>'GET'], $r->getConstraints());
  }
  
  /**
   * @covers Artax\Routing\Route::getAlias
   * @covers Artax\Routing\Route::__construct
   */
  public function testGetAliasReturnsPropertyValue()
  {
    $r = new Artax\Routing\Route('/route', 'Controller');
    $this->assertEquals('/route', $r->getAlias());
  }
  
  /**
   * @covers Artax\Routing\Route::getController
   */
  public function testGetControllerReturnsControllerProperty()
  {
    $r = new Artax\Routing\Route('/route', 'Controller');
    $this->assertEquals('Controller', $r->getController());
  }
  
  /**
   * @covers Artax\Routing\Route::getPattern
   * @covers Artax\Routing\Route::buildPattern
   * @covers Artax\Routing\Route::compile
   */
  public function testGetPatternProperty()
  {
    $r = new Artax\Routing\Route('/route', 'Controller');
    $expected = '#^/route$#u';
    $this->assertEquals($expected, $r->getPattern());
  }
  
  /**
   * @covers Artax\Routing\Route::buildPattern
   * @covers Artax\Routing\Route::compile
   */
  public function testCompiledPatternSpecifiesRouteVariables()
  {
    $r = new Artax\Routing\Route('/route/<id>', 'Controller', ['id'=>'\d+']);
    $expected = '#^/route/(?P<id>\d+)$#u';
    $this->assertEquals($expected, $r->getPattern());
  }
  
  /**
   * @covers Artax\Routing\Route::buildPattern
   * @expectedException InvalidArgumentException
   */
  public function testPatternBuilderThrowsExceptionOnDuplicateVariable()
  {
    $r = new Artax\Routing\Route('/route/<id>/<id>', 'Controller', ['id'=>'\d+']);
  }
  
  /**
   * @covers Artax\Routing\Route::buildPattern
   * @expectedException InvalidArgumentException
   */
  public function testPatternBuilderThrowsExceptionOnMissingVariableConstraint()
  {
    $r = new Artax\Routing\Route('/route/<id>', 'Controller', ['test'=>'\d+']);
  }
}
