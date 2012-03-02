<?php

/**
 * Artax HTTP Package HeaderBucket Class File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    blocks
 * @subpackage http
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\http {

  /**
   * HTTP HeaderBucket Class
   * 
   * The class does not perform any processing on the HTTP headers; it simply
   * stores them in a key=>value container.
   * 
   * @category   artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class HeaderBucket extends BucketAbstract
  {
    /**
     * Auto-detect HTTP headers and populate associated bucket params
     * 
     * @param array $_server An optional SERVER value array
     * 
     * @return Object instance for method chaining
     */
    public function detect($_server=NULL) {
      $this->clear();
      $headers = $this->getRequestHeaders($_server);
      $this->load($headers);
      return $this;
    }
    
    /**
     * Generate associative array of submitted HTTP request headers
     * 
     * @param array $_server An optional SERVER value array
     * 
     * @return array Returns a key=>value array of submitted HTTP request headers
     */
    protected function getRequestHeaders($_server=NULL)
    {
      if ($_server || ! $headers = $this->nativeHeaderGet()) {
        $_server = $_server ? $_server : $_SERVER;
        $headers = [];
        $hdrVars = ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'];
        foreach ($_server as $name => $value) { 
          if (0 === strpos($name, 'HTTP_')) {
            $name = substr($name, 5);
            $headers[$this->formatHeaderNames($name)] = $value; 
          } elseif (in_array($name, $hdrVars)) { 
            $headers[$this->formatHeaderNames($name)] = $value; 
          } 
        }
        // Cookies are stored in `$_SERVER['HTTP_COOKIE']`. We remove it because
        // we want to store the cookie data in its own bucket.
        unset($headers['Cookie']);
      }
      return $headers;
    }
    
    /**
     * Retrieve a list of headers using the native apache function if available
     * 
     * @return array Returns an array of header values
     */
    protected function nativeHeaderGet()
    {
      return function_exists('getallheaders') ? getallheaders() : [];
    }
    
    /**
     * Format header names
     * 
     * @param string $name An unformatted header name string
     * 
     * @return array Returns an array of header values
     */
    protected function formatHeaderNames($name)
    {
      $r = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))));
      return $r;
    }
  }
}
