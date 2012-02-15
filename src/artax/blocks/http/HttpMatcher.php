<?php

/**
 * Artax Route HttpMatcher Class File
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
   * Route HttpMatcher Class
   * 
   * @category   artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class HttpMatcher extends \artax\Matcher
  {
    /**
     * Extends parent to allow HTTP method route constraint
     * 
     * @param \artax\RequestInterface $request The request to match
     * @param \artax\RouteInterface   $route   The route object to match against
     * 
     * @return bool Returns `TRUE` on match or `FALSE` if no match
     */
    protected function matchRoute(\artax\RequestInterface $request,
      \artax\RouteInterface $route)
    {
      $constraints = $route->getConstraints();
      $httpMethod  = $request->getMethod();
      
      if ( ! empty($constraints['_method']) && $constraints['_method'] !== $httpMethod) {
        return FALSE;
      } else {
        return parent::matchRoute($request, $route);
      }
    }
  }
}
