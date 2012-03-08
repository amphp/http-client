<?php

namespace Controllers {
  
  class ExHandler extends ControllerAbstract
  {
    /**
     * An uncaught exception object
     * @var Exception
     * @see ExHandler::setException
     */
    protected $exception;
    
    /**
     * A debug output display flag
     * @var bool
     * @see ExHandler::setDebug
     */
    protected $debug;
    
    /**
     * Builds the ExHandler controller response
     * 
     * @return ExHandler Returns response-populated controller object
     */
    public function exec()
    {
      $body = $this->exception && $this->debug
        ? PHP_EOL . (string) $this->exception . PHP_EOL
        : PHP_EOL . 'Oh no! You broke it!' . PHP_EOL;
      
      $this->response->set($body);
      return $this;
    }
    
    /**
     * Setter method to inject the uncaught exception
     * 
     * @param Exception $e An uncaught exception object
     * 
     * @return ExHandler Returns object instance for method chaining
     */
    public function setException(\Exception $e)
    {
      $this->exception = $e;
      return $this;
    }
    
    /**
     * Set the debug output flag for handler messages
     * 
     * @param bool $debug Whether or not debug output should be sent to client
     * 
     * @return ExHandler Returns object instance for method chaining
     */
    public function setDebug($debug)
    {
      $this->debug = (bool) $debug;
      return $this;
    }
  }
}
