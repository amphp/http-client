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
     * @param \artax\RequestInterface $request The request object to match
     * 
     * @return mixed Returns the return executed controller
     * @throws \artax\exceptions\RequestNotFoundException If no route is matched
     */
    public function dispatch(\artax\RequestInterface $request)
    {
      if ($this->matcher->match($request)) {
        $controller = $this->matcher->getController();
        $customArgs = ['request'=>$request];
        $obj = $this->deps->make($controller, $customArgs);
        return $obj->exec($this->matcher->getArgs());
      } else {
        throw new \artax\exceptions\RequestNotFoundException;
      }
    }
  }
}
