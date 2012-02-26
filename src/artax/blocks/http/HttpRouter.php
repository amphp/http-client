<?php

/**
 * HttpRouter Class File
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
   * HttpRouter Class
   * 
   * @category   artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class HttpRouter extends \artax\RouterAbstract
  {
    /**
     * Loads, executes and returns the controller for the specified request
     * 
     * @return mixed Returns the return executed controller
     * @notifies ax.request.not_found
     */
    public function dispatch()
    {
      if ($this->matcher->match($this->request, $this->routeList)) {
        $controller = $this->matcher->getController();
        $obj = $this->deps->make($controller);
        return $obj->exec($this->matcher->getArgs());
      } else {
        $this->notify('ax.http_router.request_not_found');
        return NULL;
      }
    }
  }
}
