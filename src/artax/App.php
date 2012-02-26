<?php

/**
 * Artax App Class File
 * 
 * PHP version 5.4
 * 
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {
  
  /**
   * Artax Application Class
   * 
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class App implements NotifierInterface
  {
    use NotifierTrait;
    
    /**
     * @var ConfigLoader
     */
    protected $configLoader;
    
    /**
     * @var ConfigInterface
     */
    protected $config;
    
    /**
     * @var ErrorHandlerInterface
     */
    protected $errorHandler;
    
    /**
     * @var FatalHandlerInterface
     */
    protected $fatalHandler;
    
    /**
     * @var ClassLoaderFactory
     */
    protected $clFactory;
    
    /**
     * @var RouteList
     */
    protected $routes;
    
    /**
     * @var ProviderInterface
     */
    protected $depProvider;
    
    /**
     * @var array
     */
    protected $bootSteps;
    
    /**
     * Constructor injects object dependencies
     * 
     * @param ConfigLoader $configLoader
     * @param Config       $config
     * @param ErrorHandler $errorHandler
     * @param FatalHandler $fatalHandler
     * @param DepProvider  $depProvider
     * @param Mediator     $mediator
     * @param RoutesList   $routes
     * 
     * @return void
     */
    public function __construct(
      ConfigLoader $configLoader,
      Config $config,
      ErrorHandlerInterface $errorHandler,
      FatalHandlerInterface $fatalHandler,
      ClassLoaderFactory $clFactory,
      DepProvider $depProvider,
      Mediator $mediator,
      RouteList $routes
    )
    {
      $this->errorHandler = $errorHandler;
      $this->fatalHandler = $fatalHandler;
      $this->configLoader = $configLoader;
      $this->config       = $config;
      $this->depProvider  = $depProvider;
      $this->clFactory    = $clFactory;
      $this->mediator     = $mediator;
      $this->routes       = $routes;
      
      $this->bootSteps = [
        'initErrorHandler',
        'initFatalHandler',
        'initConfig',
        'loadBundles',
        'loadAutoRequires',
        'initClassAutoloaders',
        'initDepProvider',
        'initMediator',
        'initRoutes'
      ];
    }
    
    /**
     * 
     */
    public function boot()
    {
      foreach ($this->bootSteps as $step) {
        $this->$step();
      }
      $this->notify('ax.boot.complete');
    }
    
    /**
     * Initialize an error handler to throw exceptions on PHP errors
     * 
     * @return void
     */
    public function initErrorHandler()
    {
      set_error_handler([$this->errorHandler, 'handle']);
    }
    
    /**
     * Initialize exception/shutdown handler
     * 
     * The dot-notation class name is retrieved from the config property and
     * instantiated using the dependency provider.
     * 
     * @return void
     */
    public function initFatalHandler()
    {
      set_exception_handler([$this->fatalHandler, 'exHandler']);
      register_shutdown_function([$this->fatalHandler, 'shutdown']);
    }
    
    /**
     * Load app configuration directives
     * 
     * @return void
     */
    public function initConfig()
    {
      $configArr = $this->configLoader->load()->getConfigArr();
      $this->config->load($configArr, TRUE);
      
      if (empty($this->config['debug'])) {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
        ini_set('display_errors', FALSE);
      } else {
        ini_set('display_errors', TRUE);
        $this->fatalHandler->setDebug(TRUE);
      }
    }
    
    /**
     * Load optional lib bundles
     * 
     * @return void
     */
    public function loadBundles()
    {
      if ( ! empty($this->config['cacheBundle'])) {
        require AX_SYSTEM_DIR . '/src/artax/blocks/cache/CacheDriverInterface.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/cache/CacheableInterface.php';
      }
      
      if ( ! empty($this->config['httpBundle'])) {
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
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpControllerAbstract.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpResponseInterface.php';
        require AX_SYSTEM_DIR . '/src/artax/blocks/http/HttpResponse.php';
      }
      
      if ( ! empty($this->config['cliBundle'])) {
        // load cli libs
      }
    }
    
    /**
     * Registers Artax class loader and any other specified namespace loaders
     * 
     * @return void
     */
    public function initClassAutoloaders()
    {
      $type = empty($this->config['classLoader'])
        ? 'standard'
        : $this->config['classLoader'];
      
      $this->clFactory->make($type, 'artax')
           ->setIncludePath(AX_SYSTEM_DIR.'/src')
           ->register();
      
      if (isset($this->config['namespaces'])) {
        foreach ($this->config['namespaces'] as $ns => $path) {
           $this->clFactory->make($type, $ns)->setIncludePath($path)->register();
        }
      }
    }
    
    /**
     * Require a user-specified list of includes
     * 
     * @return void
     */
    public function loadAutoRequires()
    {
      if (isset($this->config['autoRequire'])) {
        foreach ($this->config['autoRequire'] as $file) {
          require $file;
        }
      }
    }
    
    /**
     * Load specified dependencies
     * 
     * @return void
     */
    public function initDepProvider()
    {
      if (isset($this->config['deps']) && $d = $this->config['deps']) {
        $depsArr = is_array($d)
          ? $d
          : $this->configLoader->load($d)->getConfigArr();
        $this->depProvider->load($depsArr);
      }
      $this->depProvider->setSharedDep('artax.DepProvider', $this->depProvider);
    }
    
    /**
     * Load mediator and specified event listeners
     * 
     * @return void
     */
    public function initMediator()
    {
      $listeners = isset($this->config['listeners'])
        ? $this->config['listeners']
        : [];
      
      if ($listeners) {
        foreach ($listeners as $listener) {
          $lambda = \Closure::bind($listener[1], $this);
          $this->mediator->push($listener[0], $lambda);
        }
      }
      $this->depProvider->setSharedDep('artax.Mediator', $this->mediator);
      $this->notify('ax.boot.listeners_loaded');
    }
    
    /**
     * Load config routes
     * 
     * @return void
     */
    public function initRoutes()
    {
      if (isset($this->config['routes'])) {
        $routes    = $this->config['routes'];
        $routesArr = is_array($routes)
          ? $routes
          : $this->configLoader->setConfigFile($routes)->load()->getConfigArr();
        $this->routes->addAllFromArr($routesArr);
      }
      $this->depProvider->setSharedDep('artax.RouteList', $this->routes);
      $this->notify('ax.boot.routes_loaded');
    }
    
    /**
     * Exposes magic access to protected/private object properties
     * 
     * @param string $prop Object property name
     * 
     * @return mixed Returns the value of the requested property if it exists
     * @throws exceptions\OutOfBoundsException On non-existent property request
     */
    public function __get($prop)
    {
      if (property_exists($this, $prop)) {
        return $this->$prop;
      } else {
        $msg = "Invalid property: artax\App::\$$prop does not exist";
        throw new exceptions\OutOfBoundsException($msg);
      }
    }
  }
}
