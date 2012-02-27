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
  class HttpMatcher extends \artax\routing\Matcher
  {
    /**
     * Extends parent to allow HTTP method route constraint
     * 
     * @param \artax\routing\RequestInterface $request The request to match
     * @param \artax\routing\RouteInterface   $route   The route to match against
     * 
     * @return bool Returns `TRUE` on match or `FALSE` if no match
     */
    protected function matchRoute(
      \artax\routing\RequestInterface $request,
      \artax\routing\RouteInterface $route)
    {
      $constraints = $route->getConstraints();
      $httpMethod  = $request->getMethod();
      
      if ( ! empty($constraints['_method'])
        && $constraints['_method'] !== $httpMethod) {
        return FALSE;
      } else {
        return parent::matchRoute($request, $route);
      }
    }
  }
}
