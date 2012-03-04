<?php

class RouteTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\routing\Route::getConstraints
   * @covers artax\routing\Route::__construct
   */
  public function testGetConstraintsReturnsConstraintsProperty()
  {
    $r = new artax\routing\Route('/route', 'Controller');
    $this->assertEquals([], $r->getConstraints());
    
    $r = new artax\routing\Route('/route', 'Controller', ['_method'=>'GET']);
    $this->assertEquals(['_method'=>'GET'], $r->getConstraints());
  }
  
  /**
   * @covers artax\routing\Route::getAlias
   * @covers artax\routing\Route::__construct
   */
  public function testGetAliasReturnsPropertyValue()
  {
    $r = new artax\routing\Route('/route', 'Controller');
    $this->assertEquals('/route', $r->getAlias());
  }
  
  /**
   * @covers artax\routing\Route::getController
   */
  public function testGetControllerReturnsControllerProperty()
  {
    $r = new artax\routing\Route('/route', 'Controller');
    $this->assertEquals('Controller', $r->getController());
  }
  
  /**
   * @covers artax\routing\Route::getPattern
   * @covers artax\routing\Route::buildPattern
   * @covers artax\routing\Route::compile
   */
  public function testGetPatternProperty()
  {
    $r = new artax\routing\Route('/route', 'Controller');
    $expected = '#^/route$#u';
    $this->assertEquals($expected, $r->getPattern());
  }
  
  /**
   * @covers artax\routing\Route::buildPattern
   * @covers artax\routing\Route::compile
   */
  public function testCompiledPatternSpecifiesRouteVariables()
  {
    $r = new artax\routing\Route('/route/<id>', 'Controller', ['id'=>'\d+']);
    $expected = '#^/route/(?P<id>\d+)$#u';
    $this->assertEquals($expected, $r->getPattern());
  }
  
  /**
   * @covers artax\routing\Route::buildPattern
   * @expectedException InvalidArgumentException
   */
  public function testPatternBuilderThrowsExceptionOnDuplicateVariable()
  {
    $r = new artax\routing\Route('/route/<id>/<id>', 'Controller', ['id'=>'\d+']);
  }
  
  /**
   * @covers artax\routing\Route::buildPattern
   * @expectedException InvalidArgumentException
   */
  public function testPatternBuilderThrowsExceptionOnMissingVariableConstraint()
  {
    $r = new artax\routing\Route('/route/<id>', 'Controller', ['test'=>'\d+']);
  }
}
