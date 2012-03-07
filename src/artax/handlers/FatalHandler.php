<?php

/**
 * Artax FatalHandler Class File
 *
 * PHP version 5.4
 *
 * @category   artax
 * @package    core
 * @subpackage handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
namespace artax\handlers {

  /**
   * FatalHandler Class
   *
   * Provides unexpected exception and fatal error handling functionality.
   *
   * @category   artax
   * @package    core
   * @subpackage handlers
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class FatalHandler implements FatalHandlerInterface,
    \artax\events\NotifierInterface
  {
    use \artax\events\NotifierTrait;
    
    /**
     * Flag specifying if full debug output should be shown when problems arise
     * @var bool
     */
    protected $debug = TRUE;
    
    /**
     * The "last chance" handler for uncaught exceptions 
     * 
     * If the object has been injected with an active Mediator, the exception
     * will be emitted to any attached event listeners. If not, the default
     * handler message will be output. It is important that any events listening
     * for uncaught exceptions prevent any of their own thrown exceptions from
     * bubbling up the stack, otherwise the default message will be displayed.
     * 
     * Note that the shutdown handler will still be invoked after handling of an
     * uncaught exception.
     * 
     * @param Exception $e Exception object
     *
     * @return void
     * @uses FatalHandler::setException
     * @notifies app.exception|\Exception $e
     */
    public function exHandler(\Exception $e)
    {
      if ($e instanceof \artax\exceptions\ScriptHaltException) {
        return;
      } elseif (NULL !== $this->mediator) {
        try {
          $this->notify('app.exception', $e);
        } catch (\Exception $e) {
          echo $this->defaultHandlerMsg($e);
        }
      } else {
        echo $this->defaultHandlerMsg($e);
      }
    }

    /**
     * Handle unexpected fatal errors and/or notify listeners of shutdown
     * 
     * If script shutdown was caused by a fatal PHP error, the error is used to 
     * generate a corresponding `ErrorException` object which is then passed to
     * `FatalHandler::exHandler` for handling.
     * 
     * The mediator is notified on shutdown so that any interested
     * listeners can act appropriately. If an event listener invoked by this
     * notification throws an uncaught exception it will not be handled and
     * script execution will cease immediately without sending further output.
     * 
     * @return void
     * @uses FatalHandler::getFatalErrException
     * @uses FatalHandler::exHandler
     * @notifies app.tearDown|\artax\handlers\FatalHandler
     */
    public function shutdown()
    {
      if ($e = $this->getFatalErrException()) {
        $this->exHandler($e);
      } elseif (NULL !== $this->mediator) {
        try {
          $this->notify('app.tearDown');
        } catch (\Exception $e) {
        }
      }
    }
    
    /**
     * Setter method for protected `$debug` property
     * 
     * @param bool $debug Debug flag
     * 
     * @return FatalHandler Returns object instance for method chaining
     */
    public function setDebug($debug)
    {
      $this->debug = (bool) $debug;
      return $this;
    }
    
    /**
     * Setter injection method for protected $mediator property
     * 
     * Setter injection is used over constructor injection here because we want
     * to implement the class exception and shutdown handlers as early as possible
     * in the boot process. The event mediator is then injected once event all
     * listeners are loaded from the app configuration settings.
     * 
     * The mediator property is provided by the usage of NotifierTrait.
     * 
     * @param Mediator $mediator An event mediator object instance
     * 
     * @return FatalHandler Returns object instance for method chaining.
     */
    public function setMediator(\artax\events\Mediator $mediator)
    {
      $this->mediator = $mediator;
      return $this;
    }

    /**
     * Determine if the last triggered PHP error was fatal
     * 
     * If the last occuring error during script execution was fatal the function
     * returns an `ErrorException` object representing the error so it can be
     * handled by `FatalHandler::exHandler`.
     * 
     * @return mixed Returns NULL if no error occurred or a non-fatal error was 
     *               raised. An ErrorException is returned if the last error
     *               raised was fatal.
     * @used-by FatalHandler::shutdown
     */
    public function getFatalErrException()
    {
      $ex  = NULL;
      $err = $this->lastError();
      
      $fatals = [
        E_ERROR           => 'Fatal Error',
        E_PARSE           => 'Parse Error',
        E_CORE_ERROR      => 'Core Error',
        E_CORE_WARNING    => 'Core Warning',
        E_COMPILE_ERROR   => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning'
      ];
      
      if (isset($fatals[$err['type']])) {
        $msg = $fatals[$err['type']] . ': ' . $err['message'] . ' in ';
        $msg.= $err['file'] . ' on line ' . $err['line'];
        $ex = new \ErrorException($msg);
      }
      return $ex;
    }
    
    /**
     * Get an array representation of the most recently raised PHP error
     * 
     * This method exists primarily for testing purposes to allow mocking
     * of its behavior.
     * 
     * @return array Returns an associative error representation array
     * @used-by FatalHandler::getFatalErrException
     */
    protected function lastError()
    {
      return error_get_last();
    }
    
    /**
     * Default exception message if no custom handling controller specified
     * 
     * @param Exception $e An uncaught exception object
     * 
     * @return string Returns a message appropriate to the object's debug setting
     */
    protected function defaultHandlerMsg(\Exception $e)
    {
      return $this->debug
        ? (string) $e
        : 'Yikes. There\'s an internal error and we\'re working to fix it.';
    }
  }
}
