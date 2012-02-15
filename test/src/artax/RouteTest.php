<?php

class RouteTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\Route::getConstraints
   * @covers artax\Route::__construct
   */
  public function testGetConstraintsReturnsConstraintsProperty()
  {
    $r = new artax\Route('/route', 'Controller');
    $this->assertEquals([], $r->getConstraints());
    
    $r = new artax\Route('/route', 'Controller', ['_method'=>'GET']);
    $this->assertEquals(['_method'=>'GET'], $r->getConstraints());
  }
  
  /**
   * @covers artax\Route::getAlias
   * @covers artax\Route::__construct
   */
  public function testGetAliasReturnsPropertyValue()
  {
    $r = new artax\Route('/route', 'Controller');
    $this->assertEquals('/route', $r->getAlias());
  }
  
  /**
   * @covers artax\Route::getController
   */
  public function testGetControllerReturnsControllerProperty()
  {
    $r = new artax\Route('/route', 'Controller');
    $this->assertEquals('Controller', $r->getController());
  }
  
  /**
   * @covers artax\Route::getPattern
   * @covers artax\Route::buildPattern
   * @covers artax\Route::compile
   */
  public function testGetPatternProperty()
  {
    $r = new artax\Route('/route', 'Controller');
    $expected = '#^/route$#u';
    $this->assertEquals($expected, $r->getPattern());
  }
  
  /**
   * @covers artax\Route::buildPattern
   * @covers artax\Route::compile
   */
  public function testCompiledPatternSpecifiesRouteVariables()
  {
    $r = new artax\Route('/route/<id>', 'Controller', ['id'=>'\d+']);
    $expected = '#^/route/(?P<id>\d+)$#u';
    $this->assertEquals($expected, $r->getPattern());
  }
  
  /**
   * @covers artax\Route::buildPattern
   * @expectedException artax\exceptions\InvalidArgumentException
   */
  public function testPatternBuilderThrowsExceptionOnDuplicateVariable()
  {
    $r = new artax\Route('/route/<id>/<id>', 'Controller', ['id'=>'\d+']);
  }
  
  /**
   * @covers artax\Route::buildPattern
   * @expectedException artax\exceptions\InvalidArgumentException
   */
  public function testPatternBuilderThrowsExceptionOnMissingVariableConstraint()
  {
    $r = new artax\Route('/route/<id>', 'Controller', ['test'=>'\d+']);
  }
}















