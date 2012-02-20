<?php

/**
 * Artax Handler Class File
 *
 * PHP version 5.4
 *
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */
namespace artax {

  /**
   * Artax Handler Class
   *
   * Provides unexpected exception and fatal error handling functionality
   *
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class Handler implements HandlerInterface
  {
    /**
     * @var ControllerInterface
     */
    protected $exController;
    
    /**
     * Assigns controller object to display unexpected exceptions and fatal errors
     * 
     * @param ExControllerInterface $exController The controller to use in
     *                                            representing the issue to the
     *                                            client if an unexpected
     *                                            exception or fatal error occurs
     */
    public function __construct(ExControllerInterface $exController)
    {
      $this->exController = $exController;
    }
    
    /**
     * The "last chance" handler for uncaught exceptions
     * 
     * If the built-in ScriptHaltException is thrown the exception handler
     * will execute silently to allow script execution to end.
     * 
     * @param \Exception $e Exception object
     *
     * @return void
     */
    public function exHandler(\Exception $e)
    {
      if ( ! $e instanceof exceptions\ScriptHaltException) {
        $this->exController->setException($e);
        $this->exController->exec()->getResponse()->exec();
      }
    }

    /**
     * Handle unexpected fatal errors
     *
     * @return void
     * @uses HandlersAbstract::getFatalErrException
     */
    public function shutdown()
    {
      if ($e = $this->getFatalErrException()) {
        $this->exController->setException($e);
        $this->exController->exec()->getResponse()->exec();
      }
    }

    /**
     * Determine if the last triggered PHP error was fatal
     * 
     * If the last occuring error during script execution was fatal the function
     * returns a `artax\exceptions\RuntimeException` object representing the error
     * so it can be handled by the unexpectedError handler.
     * 
     * @return mixed Returns NULL if none/non-fatal error -or- RuntimeException if
     *               last error occurence was fatal.
     */
    public function getFatalErrException()
    {
      $ex  = NULL;
      $err = $this->lastError();
      
      if (NULL !== $err && ! stristr($err['message'], 'ScriptHaltException')) {
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
          $ex = new exceptions\RuntimeException($msg);
        }
      }
      return $ex;
    }
    
    /**
     * Get an array representation of the most recently raised PHP error
     * 
     * @return array Returns an associative error representation array
     */
    protected function lastError()
    {
      return error_get_last();
    }
  }
}
