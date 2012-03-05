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
  class App implements events\NotifierInterface
  {
    use events\NotifierTrait;
    
    /**
     * Loader object for config files
     * @var ConfigLoader
     */
    protected $configLoader;
    
    /**
     * The app config directive bucket
     * @var ConfigInterface
     */
    protected $config;
    
    /**
     * The PHP error handler class
     * @var handlers\ErrorHandlerInterface
     */
    protected $errorHandler;
    
    /**
     * The fatal error handling class
     * @var handlers\FatalHandlerInterface
     */
    protected $fatalHandler;
    
    /**
     * A factory for building class loaders
     * @var ClassLoaderFactory
     */
    protected $clFactory;
    
    /**
     * The app dependency provider
     * @var ProviderInterface
     */
    protected $depProvider;
    
    /**
     * An ordered list of class boot methods to execute
     * @var array
     */
    protected $bootSteps;
    
    /**
     * A list of shared dependencies that span the full application scope
     * @var array
     */
    protected $sharedAppDeps;
    
    /**
     * Constructor injects object dependencies
     * 
     * @param ConfigLoader          $configLoader Loader object for config files
     * @param Config                $config       The app config directive bucket
     * @param ErrorHandlerInterface $errorHandler The PHP error handler class
     * @param FatalHandlerInterface $fatalHandler The fatal error handling class
     * @param ClassLoaderFactory    $clFactory    A factory for building class loaders
     * @param DepProvider           $depProvider  The app dependency provider
     * @param Mediator              $mediator     An event mediator object
     * 
     * @return void
     */
    public function __construct(
      ConfigLoader $configLoader,
      Config $config,
      handlers\ErrorHandlerInterface $errorHandler,
      handlers\FatalHandlerInterface $fatalHandler,
      ClassLoaderFactory $clFactory,
      DepProvider $depProvider,
      events\Mediator $mediator
    )
    {
      $this->errorHandler = $errorHandler;
      $this->fatalHandler = $fatalHandler;
      $this->configLoader = $configLoader;
      $this->config       = $config;
      $this->depProvider  = $depProvider;
      $this->clFactory    = $clFactory;
      $this->mediator     = $mediator;
      
      $this->bootSteps = [
        'initErrorHandler',
        'initFatalHandler',
        'initConfig',
        'loadBundles',
        'loadAutoRequires',
        'initClassAutoloaders',
        'initDepProvider',
        'initListeners',
        'sharedAppScopeDeps'
      ];
      
      $this->sharedAppDeps = [
        'artax.Config',
        'artax.events.Mediator'
      ];
    }
    
    /**
     * Executes the application boot process
     * 
     * @return void
     * @notifies ax.boot_complete|\artax\App
     */
    public function boot()
    {
      foreach ($this->bootSteps as $step) {
        $this->$step();
      }
      $this->notify('ax.boot_complete');
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
      
      $debug = $this->config['debug'];
      
      if ( ! $debug) {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
        ini_set('display_errors', FALSE);
      } else {
        ini_set('display_errors', TRUE);
      }
      $this->fatalHandler->setDebug($debug);
    }
    
    /**
     * Load optional lib bundles
     * 
     * @return void
     */
    public function loadBundles()
    {
      if ( ! empty($this->config['httpBundle'])) {
        require AX_SYSTEM_DIR . '/src/artax/views/ViewInterface.php';
        require AX_SYSTEM_DIR . '/src/artax/http/HttpMatcher.php';
        require AX_SYSTEM_DIR . '/src/artax/http/HttpRequestInterface.php';
        require AX_SYSTEM_DIR . '/src/artax/http/HttpRequest.php';
        require AX_SYSTEM_DIR . '/src/artax/http/BucketInterface.php';
        require AX_SYSTEM_DIR . '/src/artax/http/BucketAbstract.php';
        require AX_SYSTEM_DIR . '/src/artax/http/ServerBucket.php';
        require AX_SYSTEM_DIR . '/src/artax/http/HeaderBucket.php';
        require AX_SYSTEM_DIR . '/src/artax/http/ParamBucket.php';
        require AX_SYSTEM_DIR . '/src/artax/http/CookieBucket.php';
        require AX_SYSTEM_DIR . '/src/artax/http/HttpControllerAbstract.php';
        require AX_SYSTEM_DIR . '/src/artax/http/HttpResponseInterface.php';
        require AX_SYSTEM_DIR . '/src/artax/http/HttpResponse.php';
      }
    }
    
    /**
     * Registers Artax class loader and any other specified namespace loaders
     * 
     * @return void
     */
    public function initClassAutoloaders()
    {
      $this->clFactory->make($this->config['classLoader'], 'artax')
           ->setIncludePath(AX_SYSTEM_DIR.'/src')
           ->register();
      
      $type = $this->config->get('classLoader');
      
      if ($namespaces = $this->config->get('namespaces')) {
        foreach ($namespaces as $ns => $path) {
          $ns = $ns ?: NULL;
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
      if ($autoRequires = $this->config->get('autoRequire')) {
        foreach ($autoRequires as $file) {
          require $file;
        }
      }
    }
    
    /**
     * Load dependencies and share application-scoped dependencies
     * 
     * @return void
     */
    public function initDepProvider()
    {
      if ($deps = $this->config->get('deps')) {
        $depsArr = is_array($deps)
          ? $deps
          : $this->configLoader->load($d)->getConfigArr();
          
        $this->depProvider->load($depsArr);
      }
      foreach ($this->sharedAppDeps as $sad) {
        $this->depProvider->set($sad, ['_shared' => TRUE]);
      }
    }
    
    /**
     * Load mediator and specified event listeners
     * 
     * @return void
     */
    public function initListeners()
    {
      if ($listeners = $this->config->get('listeners')) {
        $listenersArr = is_array($listeners)
          ? $listeners
          : $this->configLoader->setConfigFile($listeners)->load()->getConfigArr();
          
        foreach ($listeners as $listener) {
          $lambda = \Closure::bind($listener[1], $this);
          $this->mediator->push($listener[0], $lambda);
        }
      }
      $this->fatalHandler->setMediator($this->mediator);
    }
    
    /**
     * Store shared application scope dependencies in depProvider instance
     * 
     * @return void
     */
    public function sharedAppScopeDeps()
    {
      $this->depProvider->setSharedDep('artax.Config', $this->config);
      $this->depProvider->setSharedDep('artax.events.Mediator', $this->mediator);
    }
    
    /**
     * Exposes magic access to protected/private object properties
     * 
     * @param string $prop Object property name
     * 
     * @return mixed Returns the value of the requested property if it exists
     * @throws OutOfBoundsException On non-existent property request
     */
    public function __get($prop)
    {
      if (property_exists($this, $prop)) {
        return $this->$prop;
      } else {
        $msg = "Invalid property: artax\App::\$$prop does not exist";
        throw new \OutOfBoundsException($msg);
      }
    }
  }
}
