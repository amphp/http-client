<?php

class BootstrapperTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\Bootstrapper::__construct
   * @covers artax\Bootstrapper::__get
   */
  public function testConstructorAssignsProperties()
  {
    $loader      = new artax\ConfigLoader('vfs://myapp/conf/config.php');
    $config      = new artax\Config;
    $dotNotation = new artax\DotNotation;
    $handler     = new artax\ErrorHandler;
    $routes      = new artax\RouteList;
    $dp          = new artax\DepProvider(new artax\DotNotation);
    $mediator    = new artax\blocks\mediator\Mediator;
      
    $b = new artax\Bootstrapper($loader, $config, $dotNotation, $handler,
      $routes, $dp, $mediator);
    
    $this->assertEquals($loader, $b->configLoader);
    $this->assertEquals($config, $b->config);
    $this->assertEquals($dotNotation, $b->dotNotation);
    $this->assertEquals($handler, $b->errorHandler);
    $this->assertEquals($routes, $b->routes);
    $this->assertEquals($dp, $b->depProvider);
    
    return $b;
  }
  
  /**
   * @depends testConstructorAssignsProperties
   * @covers  artax\Bootstrapper::initErrorHandler
   */
  public function testInitErrorHandler($bootstrapper)
  {
    restore_error_handler();
    $bootstrapper->initErrorHandler();
    try {
      $ex = FALSE;
      $arr = [];
      $test = $arr['test'];
    } catch(artax\exceptions\ErrorException $e) {
      $ex = TRUE;
    }
    $this->assertTrue($ex);
    return $bootstrapper;
  }
  
  /**
   * @depends testInitErrorHandler
   * @covers  artax\Bootstrapper::initConfig
   */
  public function testInitConfig($bootstrapper)
  {
    $bootstrapper->initConfig();
    
    $cfg = 'vfs://myapp/conf/config_no_debug.php';
    $bootstrapper->configLoader->setConfigFile($cfg);
    $bootstrapper->initConfig();
  }
}















