<?php

/**
 * Artax Config Class File
 * 
 * PHP version 5.4
 * 
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {

  /**
   * Artax Configuration class
   * 
   * Class uses BucketSettersTrait to enable setter methods for bucket
   * parameters.
   * 
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class Config extends Bucket
  {
    use BucketSettersTrait;
    
    /**
     * Initializes default configuration directive values
     * 
     * @param array $params     An array of configuration key/value parameters
     * 
     * @return void
     */
    public function __construct(Array $params=NULL)
    {
      $defaults = [
        'debug'       => TRUE,
        'httpBundle'  => TRUE,
        'cliBundle'   => FALSE,
        'autoloader'  => 'artax.ClassLoader',
        'handlers'    => 'artax.blocks.http.HttpHandlers',
        'matcher'     => 'artax.blocks.http.HttpMatcher',
        'router'      => 'artax.blocks.http.HttpRouter',
        'request'     => 'artax.blocks.http.HttpRequest'
      ];
      $params = $params ? array_merge($defaults, $params) : $defaults;
      $this->load($params);
    }
    
    /**
     * Filters boolean values
     * 
     * @param bool $val         Boolean value flag
     * 
     * @return bool Returns filtered boolean value
     */
    protected function filterBool($val)
    {
      return filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
    
    /**
     * Setter function for debug directive
     * 
     * The bootstrapper will set PHP's error reporting level based on the
     * debug value.
     * 
     * @param bool $val         A boolean debug flag
     * 
     * @return void
     */
    protected function setDebug($val)
    {
      $this->params['debug'] = $this->filterBool($val);
    }
    
    /**
     * Setter function for httpBundle directive
     * 
     * @param bool $val         Flag specifying if HTTP libs should load on boot
     * 
     * @return void
     */
    protected function setHttpBundle($val)
    {
      $this->params['httpBundle'] = $this->filterBool($val);
    }
    
    /**
     * Setter function for cliBundle directive
     * 
     * @param bool $val Flag specifying if CLI libs should be loaded on boot
     * 
     * @return void
     */
    protected function setCliBundle($val)
    {
      $this->params['cliBundle'] = $this->filterBool($val);
    }
  }
}
