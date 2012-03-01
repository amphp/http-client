<?php

/**
 * Artax ResponseControllerTrait File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @subpackage controllers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\controllers {
  
  /**
   * ResponseControllerTrait
   * 
   * Specifies functionality for implementing the ResponseControllerInterface.
   * 
   * @category   artax
   * @package    core
   * @subpackage controllers
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  trait ResponseControllerTrait
  {
    /**
     * An Response object
     * @var Response
     */
    protected $response;
    
    /**
     * Setter method for $response object property
     * 
     * @param ResponseInterface $response Response object instance
     * 
     * @return Response Returns the controller's Response object instance
     */
    public function setResponse(ResponseInterface $response)
    {
      $this->response = $response;
      return $this;
    }
    
    /**
     * Getter method for $response object property
     * 
     * @return Response Returns the controller's Response object instance
     */
    public function getResponse()
    {
      return $this->response;
    }
  }
}
