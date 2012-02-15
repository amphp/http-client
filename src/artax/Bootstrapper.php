<?php

/**
 * Artax BootStrapper Class File
 * 
 * PHP version 5.4
 * 
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {
  
  /**
   * Bootstrapper Class
   * 
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class BootStrapper
  {
    /**
     * @var ConfigLoader
     */
    protected $configLoader;
    
    /**
     * @var ConfigInterface
     */
    protected $config;
    
    /**
     * @var Handlers
     */
    protected $handlers;
    
    /**
     * @var RouteList
     */
    protected $routes;
    
    /**
     * @var Bucket
     */
    protected $bucket;
    
    /**
     * @var ProviderInterface
     */
    protected $deps;
    
    /**
     * Constructor injects object dependencies
     * 
     * @param ConfigLoader       $configLoader
     * @param ConfigInterface    $config
     * @param HandlersInterface  $handlers
     * @param RoutesList         $routes
     * @param Bucket             $bucket
     * 
     * @return void
     */
    public function __construct(
      ConfigLoader $configLoader,
      Config $config,
      HandlersInterface $handlers,
      RouteList $routes,
      Bucket $bucket,
      ProviderInterface $deps=NULL)
    {
      $this->configLoader = $configLoader;
      $this->config       = $config;
      $this->handlers     = $handlers;
      $this->routes       = $routes;
      $this->bucket       = $bucket;
      $this->deps         = $deps;
    }
    
    /**
     * Initialize an error handler to throw exceptions on PHP errors
     * 
     * @return void
     */
    public function initErrHandler()
    {
      set_error_handler(function($errno, $errstr, $errfile, $errline) {
        $levels = [
          E_WARNING           => 'Warning',
          E_NOTICE            => 'Notice',
          E_USER_ERROR        => 'User Error',
          E_USER_WARNING      => 'User Warning',
          E_USER_NOTICE       => 'User Notice',
          E_STRICT            => 'Runtime Notice',
          E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
          E_DEPRECATED        => 'Deprecated Notice',
          E_USER_DEPRECATED   => 'User Deprecated Notice'
        ];
        $msg = $levels[$errno] . ": $errstr in $errfile on line $errline";
        throw new exceptions\ErrorException($msg);
      });
      return $this;
    }
    
    /**
     * Load app configuration
     * 
     * @return Bootstrapper Object instance for method chaining
     */
    public function initConfig()
    {
      $configArr = $this->configLoader->getConfigArr();
      $this->config->load($configArr);
      $this->handlers->setConfig($this->config);
      $this->bucket->set('ax.sys.config', $this->config);
      $this->bucket->set('ax.sys.handlers', $this->handlers);
      return $this;
    }
    
    /**
     * Load config routes
     * 
     * @return Bootstrapper Object instance for method chaining
     */
    public function initRoutes()
    {
      if (isset($this->config['routes']) && $r = $this->config['routes']) {
        $routesArr = is_array($r) ? $r : $this->configLoader->load($r)->getConfigArr();
        $this->routes->addAllFromArr($routesArr);
      }
      $this->bucket->set('ax.sys.routes', $this->routes);
      return $this;
    }
    
    /**
     * Load specified dependencies
     * 
     * @return Bootstrapper Object instance for method chaining
     */
    public function initdeps()
    {
      if (isset($this->config['deps']) && $d = $this->config['deps']) {
        $depsArr = is_array($d) ? $d : $this->configLoader->load($d)->getConfigArr();
        $this->deps->load($depsArr);
      }
      $this->bucket->set('ax.sys.deps', $this->deps);
      return $this;
    }
    
    /**
     * 
     */
    public function getBucket()
    {
      return $this->bucket;
    }
  }
}
