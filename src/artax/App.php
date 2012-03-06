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
    protected $clsLoaderFactory;
    
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
     * @param ClassLoaderFactory    $clsLoaderFactory A factory for building class loaders
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
      ClassLoaderFactory $clsLoaderFactory,
      DepProvider $depProvider,
      events\Mediator $mediator
    )
    {
      $this->errorHandler     = $errorHandler;
      $this->fatalHandler     = $fatalHandler;
      $this->configLoader     = $configLoader;
      $this->config           = $config;
      $this->depProvider      = $depProvider;
      $this->clsLoaderFactory = $clsLoaderFactory;
      $this->mediator         = $mediator;
      
      $this->bootSteps = [
        'registerHandlers',
        'initConfig',
        'loadBundles',
        'loadAutoRequires',
        'initClassAutoloaders',
        'initDepProvider',
        'initEvents'
      ];
    }
    
    /**
     * Boot the application
     * 
     * @return void
     * @notifies ax.boot_complete|\artax\App
     */
    public function boot()
    {
      while ($step = array_shift($this->bootSteps)) {
        $this->$step();
      }
      $this->notify('ax.boot_complete');
      return $this;
    }
    
    /**
     * Register error, exception and shutdown handlers
     * 
     * @return void
     */
    protected function registerHandlers()
    {
      set_error_handler([$this->errorHandler, 'handle']);
      set_exception_handler([$this->fatalHandler, 'exHandler']);
      register_shutdown_function([$this->fatalHandler, 'shutdown']);
    }
    
    /**
     * Load app configuration directives
     * 
     * @return void
     */
    protected function initConfig()
    {
      $configArr = $this->configLoader->load()->getConfigArr();
      $this->config->load($configArr, TRUE);
      
      if ( ! $this->config['debug']) {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
        ini_set('display_errors', FALSE);
      } else {
        ini_set('display_errors', TRUE);
      }
      $this->fatalHandler->setDebug($this->config['debug']);
    }
    
    /**
     * Load optional lib bundles
     * 
     * @return void
     */
    protected function loadBundles()
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
    protected function initClassAutoloaders()
    {
      $this->clsLoaderFactory->make($this->config['classLoader'], 'artax')
           ->setIncludePath(AX_SYSTEM_DIR.'/src')
           ->register();
      
      $type = $this->config->get('classLoader');
      
      if ($namespaces = $this->config->get('namespaces')) {
        foreach ($namespaces as $ns => $path) {
          $ns = $ns ?: NULL;
          $this->clsLoaderFactory->make($type, $ns)->setIncludePath($path)->register();
        }
      }
    }
    
    /**
     * Require a user-specified list of includes
     * 
     * @return void
     */
    protected function loadAutoRequires()
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
    protected function initDepProvider()
    {
      if ($deps = $this->config->get('deps')) {
        $this->depProvider->load($deps);
      }
      
      $this->depProvider->set('artax.DepProvider', ['_shared' => TRUE]);
      $this->depProvider->set('artax.Config', ['_shared' => TRUE]);
      $this->depProvider->set('artax.events.Mediator', ['_shared' => TRUE]);
      
      $this->depProvider->setSharedDep('artax.DepProvider', $this->depProvider);
      $this->depProvider->setSharedDep('artax.Config', $this->config);
      $this->depProvider->setSharedDep('artax.events.Mediator', $this->mediator);
    }
    
    /**
     * Load specified event listeners and inject Mediator
     * 
     * The mediator object is injected with a Closure that rebinds each new
     * listener to the App object instance. This grants listeners public scope
     * access via `$this` to application-scope dependencies.
     * 
     * Additionally, the `App::$fatalHandler` is injected with the newly populated
     * Mediator to allow handling exceptions and shutdowns with chainable event
     * listeners.
     * 
     * @return void
     */
    protected function initEvents()
    {
      $this->mediator->setRebinder(function($lambda){
        return \Closure::bind($lambda, $this);
      });
      
      if ($listeners = $this->config->get('listeners')) {
        foreach ($listeners as $listener) {
          $this->mediator->push($listener[0], $listener[1]);
        }
      }
      
      $this->fatalHandler->setMediator($this->mediator);
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
