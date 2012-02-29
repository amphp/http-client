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
    public function __construct(array $params=[])
    {
      $this->defaults = [
        'debug'       => FALSE,
        'cacheBundle' => FALSE,
        'httpBundle'  => TRUE,
        'classLoader' => 'standard',
        'namespaces'  => [],
        'autoRequire' => []
      ];
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
      $var = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
      return (bool) $var;
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
  }
}
