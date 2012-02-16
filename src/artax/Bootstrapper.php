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
     * @var DotNotation
     */
    protected $dotNotation;
    
    /**
     * @var RouteList
     */
    protected $routes;
    
    /**
     * @var ProviderInterface
     */
    protected $depProvider;
    
    /**
     * Constructor injects object dependencies
     * 
     * @param ConfigLoader $configLoader
     * @param Config       $config
     * @param DotNotation  $dotNotation
     * @param RoutesList   $routes
     * @param DepProvider  $depProvider
     * 
     * @return void
     */
    public function __construct(
      ConfigLoader $configLoader,
      Config $config,
      DotNotation $dotNotation,
      RouteList $routes,
      DepProvider $depProvider
    )
    {
      $this->configLoader = $configLoader;
      $this->config       = $config;
      $this->dotNotation  = $dotNotation;
      $this->routes       = $routes;
      $this->depProvider  = $depProvider;
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
     * Load app configuration directives
     * 
     * @return Bootstrapper Object instance for method chaining
     */
    public function initConfig()
    {
      $configArr = $this->configLoader->getConfigArr();
      $this->config->load($configArr, TRUE);
      
      if ($this->config['debug']) {
        ini_set('display_errors', TRUE);
      } else {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
        ini_set('display_errors', FALSE);
      }
      return $this;
    }
    
    /**
     * Load optional lib bundles
     * 
     * @return Bootstrapper Object instance for method chaining
     */
    public function loadBundles()
    {
      if ($this->config['httpBundle']) {
        require AX_SYSTEM_DIR . '/src/artax/blocks/views/ViewInterface.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpMatcher.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpRouter.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpRequest.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/BucketInterface.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/BucketAbstract.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/ServerBucket.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/HeaderBucket.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/ParamBucket.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/CookieBucket.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpControllerInterface.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpControllerAbstract.php';
        require AX_SYSTEM_DIR . '/src/artax/ResponseInterface.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpResponseInterface.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpResponse.php';
      }
      if ($this->config['cliBundle']) {
        // load cli libs
      }
      return $this;
    }
    
    /**
     * Registers Artax class loader and any other specified namespace loaders
     * 
     * @return Bootstrapper Object instance for method chaining
     */
    public function initClassAutoloaders()
    {
      if ($this->config->exists('autoloader')) {
        $loader = $this->dotNotation->parse($this->config['autoloader']);
      } else {
        $msg = 'No autoloader specified';
        throw new exceptions\ConfigException($msg);
      }
      
      (new $loader('artax', AX_SYSTEM_DIR.'/src'))->register();
      
      if ($this->config->exists('namespaces')) {
        foreach ($this->config['namespaces'] as $ns => $path) {
          (new $loader($ns, $path))->register();
        }
      }
      return $this;
    }
    
    /**
     * Load specified dependencies
     * 
     * @return Bootstrapper Object instance for method chaining
     */
    public function initDepProvider()
    {
      if (isset($this->config['deps']) && $d = $this->config['deps']) {
        $depsArr = is_array($d)
          ? $d
          : $this->configLoader->load($d)->getConfigArr();
        $this->depProvider->load($depsArr);
      }
      return $this;
    }
    
    /**
     * Initialize NotFound and UnexpectedError Handlers
     * 
     * The dot-notation class name is retrieved from the config property and
     * instantiated using the dependency provider.
     * 
     * @return Bootstrapper Object instance for method chaining
     * @throws exceptions\UnexpectedValueException On invalid handler class
     */
    public function initHandlers()
    {
      $handlers = $this->depProvider->make($this->config['handlers']);
      
      if ($handlers instanceof HandlersInterface) {
        set_exception_handler([$handlers, 'exHandler']);
        register_shutdown_function([$handlers, 'shutdown']);
      } else {
        $msg = 'Handlers class must implement artax\HandlersInterface: ' .
          get_class($handlers) . ' provided';
        throw new exceptions\UnexpectedValueException($msg);
      }
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
      return $this;
    }
    
    /**
     * Generate, route and execute the request
     * 
     * @return void
     */
    public function doRequest()
    {
      $matcher    = $this->depProvider->make($this->config['matcher'],
        ['routeList'=>$this->routes]);
      
      $router     = $this->depProvider->make($this->config['router'],
        ['deps'=>$this->depProvider, 'matcher'=>$matcher]);
      
      $request    = $this->depProvider->make($this->config['request']);
      $controller = $router->dispatch($request);
      $response   = $controller->getResponse();
      $response->exec();
    }
  }
}
