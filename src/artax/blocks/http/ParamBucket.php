<?php

/**
 * Artax HTTP Package ParamBucket Class File
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
   * HTTP ParamBucket Class
   * 
   * The class _DOES NOT_ perform any filtering or processing on the passed 
   * params; it simply stores passed values in a key=>value container. It is
   * **exceedingly** important that you verify your own input.
   * 
   * @category   artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class ParamBucket extends BucketAbstract
  {
    /**
     * Overload parent constructor so we can use two superglobals
     */
    public function __construct(Array $_get=NULL, Array $_post=NULL)
    {
      $this->detect($_get, $_post);
    }
    
    /**
     * Auto-detect HTTP request parameters and populate associated bucket params
     * 
     * @param array $_get
     * @param array $_post
     * 
     * @return Object instance for method chaining
     */
    public function detect($_get=NULL, $_post=NULL) {
      $this->clear();
      $_get = $_get ? $_get : $_GET;
      $_post = $_post ? $_post : $_POST;
      $vars = array_merge($_get, $_post);
      if ($vars) {
        $this->load($vars);
      }
      return $this;
    }
  }
}
