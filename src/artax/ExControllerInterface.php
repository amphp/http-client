<?php

/**
 * ControllerInterface File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {

  /**
   * ControllerInterface
   * 
   * @category   artax
   * @package    core
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface ExControllerInterface extends ControllerInterface
  {
    /**
     * Specify the exception that was thrown to cause controller invocation
     */
    public function setException(\Exception $e);
    
    /**
     * Specify if full debug message should be shown for unexpected exceptions
     */
    public function setDebug($val);
    
    /**
     * Getter method for $response object property
     */
    public function getResponse();
  }
}
