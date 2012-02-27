<?php

/**
 * Artax FatalHandler Class File
 *
 * PHP version 5.4
 *
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */
namespace artax {

  /**
   * Artax FatalHandler Class
   *
   * Provides unexpected exception and fatal error handling functionality.
   *
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class FatalHandler implements FatalHandlerInterface
  {
    /**
     * @var bool
     */
    protected $debug = TRUE;
    
    /**
     * @var ExControllerInterface
     */
    protected $exController;
    
    /**
     * The "last chance" handler for uncaught exceptions 
     * 
     * If a custom exception handling controller throws an exception, the class
     * will fall back to the default exception display. It is imperative that your
     * custom exception handling controller prevents any exceptions it may throw
     * from bubbling up the stack.
     * 
     * @param \Exception $e Exception object
     *
     * @return void
     * @uses FatalHandler::setException
     */
    public function exHandler(\Exception $e)
    {
      if (NULL === $this->exController) {
        echo $this->defaultHandlerMsg($e);
      } else {
        try {
          $this->exController->setException($e);
          $this->exController->exec()->getResponse()->exec();
        } catch (\Exception $e) {
          echo $this->defaultHandlerMsg($e);
        }
      }
    }

    /**
     * Handle unexpected fatal errors
     * 
     * If script shutdown was caused by a fatal PHP error, the error is used to 
     * generate a corresponding `ErrorException` object which is then passed to
     * `FatalHandler::exHandler` for handling.
     * 
     * @return void
     * @uses FatalHandler::getFatalErrException
     * @uses FatalHandler::exHandler
     */
    public function shutdown()
    {
      if ($e = $this->getFatalErrException()) {
        $this->exHandler($e);
      }
    }

    /**
     * Determine if the last triggered PHP error was fatal
     * 
     * If the last occuring error during script execution was fatal the function
     * returns an `artax\exceptions\ErrorException` object representing the error
     * so it can be handled by `FatalHandler::exHandler`.
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
        $ex = new exceptions\ErrorException($msg);
      }
      return $ex;
    }
    
    /**
     * Get an array representation of the most recently raised PHP error
     * 
     * @return array Returns an associative error representation array
     * @used-by FatalHandler::getFatalErrException
     */
    protected function lastError()
    {
      return error_get_last();
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
     * Assign controller to handle uncaught exceptions and fatal errors
     * 
     * @param ExControllerInterface $exController A controller that handles
     *                                            unexpected exceptions and
     *                                            fatal error occurs.
     * 
     * @return FatalHandler Returns object instance for method chaining
     */
    public function setExController(ExControllerInterface $exController)
    {
      $exController->setDebug($this->debug);
      $this->exController = $exController;
      return $this;
    }
    
    /**
     * Default exception message if no custom handling controller specified
     * 
     * @param \Exception $e An uncaught exception object
     * 
     * @return string Returns a message appropriate to the object's debug setting
     */
    protected function defaultHandlerMsg(\Exception $e)
    {
      return $this->debug
        ? (string) $e
        : "Yikes. There's an internal error and we're working to fix it.";
    }
  }
}
