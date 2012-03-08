<?php

namespace Controllers {
  
  /**
   * The base controller from which all other controllers inherit
   * 
   * Implements the `NotifierInterface` to enable event handling from within
   * controllers.
   */
  abstract class ControllerAbstract
    implements \Artax\Controllers\ResponseControllerInterface,
      \Artax\Events\NotifierInterface
  {
    use
      \Artax\Events\NotifierTrait,
      \Artax\Controllers\ResponseControllerTrait;
    
    /**
     * Class constructor
     * 
     * @param Mediator $mediator Event mediator object
     * @param Response $response Response object
     * 
     * @return void
     */
    public function __construct(
      \Artax\Events\Mediator $mediator,
      \Artax\Controllers\Response $response
    )
    {
      $this->mediator = $mediator;
      $this->response = $response;
    }
  }
}
