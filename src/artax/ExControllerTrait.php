<?php

namespace artax {
  
  trait ExControllerTrait
  {
    /**
     * @var \Exception
     */
    protected $exception;
    
    /**
     * @var bool
     */
    protected $debug = FALSE;
    
    /**
     * Specify the exception that was thrown to cause controller invocation
     * 
     * @param \Exception $e Exception object thrown to invoke the controller
     * 
     * @return mixed Returns object instance for method chaining
     */
    public function setException(\Exception $e)
    {
      $this->exception = $e;
      return $this;
    }
    
    /**
     * Turn debug flag on or off
     * 
     * @param bool $val
     * 
     * @return mixed Returns object instance for method chaining
     */
    public function setDebug($val)
    {
      $this->debug = (bool) $val;
      return $this;
    }
  }
}
