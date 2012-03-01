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
  abstract class HttpControllerAbstract
    implements \artax\controllers\ResponseControllerInterface
  {
    /**
     * The HTTP request object to be controlled
     * @var HttpRequestInterface
     */
    protected $request;
    
    /**
     * The view object to populate in response
     * @var \artax\views\ViewInterface
     */
    protected $view;
    
    /**
     * The response object used to return the populated view object to the client
     * @var HttpResponseInterface
     */
    protected $response;
    
    /**
     * Inject dependencies
     * 
     * @param HttpRequestInterface  $request  Request object
     * @param ViewInterface         $view     View object
     * @param HttpResponseInterface $response Response object
     */
    public function __construct(
      HttpRequestInterface $request,
      \artax\views\ViewInterface $view,
      HttpResponseInterface $response)
    {
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
  }
}
