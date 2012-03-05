<?php

namespace controllers {
  
  /**
   * The base controller from which all other controllers inherit
   * 
   * Implements the `NotifierInterface` to enable event handling from within
   * controllers.
   */
  abstract class ControllerAbstract
    implements \artax\controllers\ResponseControllerInterface,
      \artax\events\NotifierInterface
  {
    use
      \artax\events\NotifierTrait,
      \artax\controllers\ResponseControllerTrait;
    
    /**
     * Class constructor
     * 
     * @param Mediator $mediator Event mediator object
     * @param Response $response Response object
     * 
     * @return void
     */
    public function __construct(
      \artax\events\Mediator $mediator,
      \artax\controllers\Response $response
    )
    {
      $this->mediator = $mediator;
      $this->response = $response;
    }
  }
}
