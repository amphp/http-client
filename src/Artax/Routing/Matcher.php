<?php

/**
 * Matcher Class File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    core
 * @subpackage routing
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Routing {
  
  /**
   * Matcher Class
   * 
   * @category   Artax
   * @package    core
   * @subpackage routing
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class Matcher implements MatcherInterface
  {
    /**
     * The request object for which to find a route match
     * @var RequestInterface
     */
    protected $request;
    
    /**
     * The list of routes to match the request against
     * @var RouteList
     */
    protected $routeList;
    
    /**
     * Route-matched controller name
     * @var string
     */
    protected $controller;
    
    /**
     * Ordered array of arguments from a matched route
     * @var array
     */
    protected $args;
    
    /**
     * Injects request and route list dependencies
     * 
     * @param RequestInterface $request   The Request object to match
     * @param RouteList        $routeList The list of routes to match against
     * 
     * @return void
     */
    public function __construct(RequestInterface $request, RouteList $routeList)
    {
      $this->args      = [];
      $this->request   = $request;
      $this->routeList = $routeList;
    }
    
    /**
     * Find a match for the requested resource target
     * 
     * @return bool Returns `TRUE` if a matching controller was found or `FALSE`
     *              if no match was located.
     */
    public function match()
    {
      foreach ($this->routeList as $route) {
        if ($this->matchRoute($this->request, $route)) {
          return TRUE;
        }
      }
      return FALSE;
    }
    
    /**
     * Determine if the specified route alias matches a route's pattern
     * 
     * If a regex match is found any controller arguments specified in the
     * route constraints array are stored in $this->matchedArgs for retrieval
     * on controller method invocation.
     * 
     * @param RequestInterface $request The request to match
     * @param RouteInterface   $route   The route object to match against
     * 
     * @return bool Returns `TRUE` on match or `FALSE` if no match
     */
    protected function matchRoute(RequestInterface $request, RouteInterface $route)
    {
      if ( ! preg_match($route->getPattern(), $request->getTarget(), $match)) {
        return FALSE;
      } else {
        $this->controller = $route->getController();
        $constraints = $route->getConstraints();
        
        if ($constraints) {
          $arr = array_intersect_key($match, $constraints);
          $this->args = array_values($arr);
        }
        return TRUE;
      }
    }
    
    /**
     * Retrieve the matched route's dot-notation controller string
     * 
     * @return string
     */
    public function getController()
    {
      return $this->controller;
    }
    
    /**
     * Retrieve ordered array of route-matched arguments
     * 
     * @return array
     */
    public function getArgs()
    {
      return $this->args;
    }
  }
}
