<?php

/**
 * HttpControllerAbstract Class File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    blocks
 * @subpackage http
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\blocks\http {

  /**
   * HttpControllerAbstract Class
   * 
   * @category   artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  abstract class HttpControllerAbstract implements \artax\ResponseControllerInterface
  {
    use \artax\NotifierTrait;
    
    /**
     * @var \artax\blocks\views\ViewInterface
     */
    protected $request;
    
    /**
     * @var \artax\views\ViewInterface
     */
    protected $view;
    
    /**
     * @var HttpResponseInterface
     */
    protected $response;
    
    /**
     * Inject dependencies
     * 
     * @param \artax\MediatorInterface   $mediator Mediator object
     * @param HttpRequestInterface    $request  Request object
     * @param \artax\views\ViewInterface $view     View object
     * @param HttpResponseInterface      $response Response object
     */
    public function __construct(
      \artax\MediatorInterface $mediator,
      HttpRequestInterface $request,
      \artax\views\ViewInterface $view,
      HttpResponseInterface $response)
    {
      $this->mediator = $mediator;
      $this->request  = $request;
      $this->view     = $view;
      $this->response = $response;
    }
    
    /**
     * Getter method for $response object property
     * 
     * @return HttpResponseInterface Returns an HttpResponse object
     */
    public function getResponse()
    {
      return $this->response;
    }
    
    /**
     * Magic invocation method to execute the controller's work method
     */
    public function __invoke()
    {
      call_user_func_array([$this, 'exec'], func_get_args());
      return $this;
    }
  }
}
