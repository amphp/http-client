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
        'handlers'    => 'artax.blocks.http.HttpHandlers',
        'router'      => 'artax.blocks.http.HttpRouter',
        'matcher'     => 'artax.blocks.http.HttpMatcher',
        'request'     => 'artax.blocks.http.HttpRequest'
      ];
      $params = $params ? array_merge($defaults, $params) : $defaults;
      $this->load($params);
    }
    
    /**
     * Loads an array of configuration parameters
     * 
     * Overrides parent method to overwrite existing parameter values by default.
     * 
     * @param array $params     An array of configuration key/value parameters
     * 
     * @return Config Object instance for method chaining
     */
    public function load(Array $params, $overwrite=TRUE)
    {
      parent::load($params, $overwrite);
      return $this;
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
      $val = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
      return (bool) $val;
    }
    
    /**
     * Setter function for debug directive
     * 
     * The bootstrapper will set PHP's error reporting level based on the
     * debug value.
     * 
     * @param bool $val Debug flag
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
     * @param bool $val Flag specifying if HTTP libs should be loaded on boot
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
