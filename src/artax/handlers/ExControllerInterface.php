<?php

/**
 * ExControllerInterface File
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
   * ExControllerInterface
   * 
   * @category   artax
   * @package    core
   * @subpackage handlers
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface ExControllerInterface extends \artax\ResponseControllerInterface
  {
    /**
     * Specify the exception that was thrown to cause controller invocation
     */
    public function setException(\Exception $e);
    
    /**
     * Specify if full debug message should be shown for unexpected exceptions
     */
    public function setDebug($val);
  }
}
