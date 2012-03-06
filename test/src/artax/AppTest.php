<?php

class AppTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers artax\App::__construct
   */
  public function testConstructorInitializesParams()
  {
    $configLoader = new artax\ConfigLoader;
    $config       = new artax\Config;
    $errorHandler = new artax\handlers\ErrorHandler;
    $fatalHandler = new artax\handlers\FatalHandler;
    $clFactory    = new artax\ClassLoaderFactory;
    $depProvider  = new artax\DepProvider(new artax\DotNotation);
    $mediator     = new artax\events\Mediator;
    
    $constructorArgs = [
      $configLoader,
      $config,
      $errorHandler,
      $fatalHandler,
      $clFactory,
      $depProvider,
      $mediator
    ];
    
    $mockMethods = ['registerHandlers', 'initConfig', 'loadBundles', 'loadAutoRequires',
      'initClassAutoloaders', 'initDepProvider','initEvents'];
    
    $app = $this->getMock('artax\App', $mockMethods, $constructorArgs);
    
    $this->assertEquals($configLoader, $app->configLoader);
    $this->assertEquals($config, $app->config);
    $this->assertEquals($errorHandler, $app->errorHandler);
    $this->assertEquals($fatalHandler, $app->fatalHandler);
    $this->assertEquals($clFactory, $app->clsLoaderFactory);
    $this->assertEquals($depProvider, $app->depProvider);
    $this->assertEquals($mediator, $app->mediator);
    
    return $app;
  }
  
  /**
   * @depends testConstructorInitializesParams
   * @covers artax\App::boot
   */
  public function testInitDepProviderSharesApplicationScopeDependencies($app)
  {
    $app->expects($this->once())->method('registerHandlers');
    $app->expects($this->once())->method('initConfig');
    $app->expects($this->once())->method('loadBundles');
    $app->expects($this->once())->method('loadAutoRequires');
    $app->expects($this->once())->method('initClassAutoloaders');
    $app->expects($this->once())->method('initDepProvider');
    $app->expects($this->once())->method('initEvents');
    
    $return = $app->boot();
    $this->assertEquals($return, $app);
  }
}










