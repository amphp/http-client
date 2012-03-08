<?php

/**
 * Artax App Class File
 * 
 * PHP version 5.4
 * 
 * @category Artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax {
  
  /**
   * Artax Application Class
   * 
   * @category Artax
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
        'initClassAutoloader',
        'initDepProvider',
        'initEvents'
      ];
    }
    
    /**
     * Boot the application
     * 
     * Once all boot steps are completed, the `app.setup` event fires to notify
     * any user-specified "setup" listeners.
     * 
     * @return void
     * @notifies app.ready|\Artax\App
     */
    public function boot()
    {
      while ($step = array_shift($this->bootSteps)) {
        $this->$step();
      }
      $this->notify('app.setUp');
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
     * Registers Artax class loader and any other specified namespace loaders
     * 
     * @return void
     */
    protected function initClassAutoloader()
    {
      $this->clsLoaderFactory->make($this->config['classLoader'], 'Artax')
           ->setIncludePath(AX_SYSTEM_DIR.'/src')
           ->register();
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
      
      $this->depProvider->set('Artax.DepProvider', ['_shared' => TRUE]);
      $this->depProvider->set('Artax.Config', ['_shared' => TRUE]);
      $this->depProvider->set('Artax.events.Mediator', ['_shared' => TRUE]);
      
      $this->depProvider->setSharedDep('Artax.DepProvider', $this->depProvider);
      $this->depProvider->setSharedDep('Artax.Config', $this->config);
      $this->depProvider->setSharedDep('Artax.events.Mediator', $this->mediator);
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
     * Once all config listeners are loaded, a listener is attached to the end
     * of the `app.setUp` queue to fire the `app.ready` event.
     * 
     * @return void
     */
    protected function initEvents()
    {
      $this->mediator->setRebindObj($this);
            
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
        $msg = "Invalid property: Artax\App::\$$prop does not exist";
        throw new \OutOfBoundsException($msg);
      }
    }
  }
}
