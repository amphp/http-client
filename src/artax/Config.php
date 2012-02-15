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
     * 
     */
    public function __construct(Array $params=NULL)
    {
      $defaults = [
        'debug'             => TRUE,
        'timezone'          => 'GMT',
        'custom404Handler'  => NULL,
        'custom500Handler'  => NULL,
        'httpSupport'       => TRUE
      ];
      $params = $params ? array_merge($defaults, $params) : $defaults;
      $this->load($params);
    }
    
    /**
     * 
     */
    public function load(Array $params, $overwrite=TRUE)
    {
      parent::load($params, $overwrite);
      return $this;
    }
    
    /**
     * Setter function for debug directive
     * 
     * PHP Error reporting level is also set based on this value.
     * 
     * @param bool $val Debug flag
     * 
     * @return void
     */
    protected function setDebug($val)
    {
      $val = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
      $this->params['debug'] = (bool)$val;
      
      if ($val) {
        ini_set('display_errors', TRUE);
      } else {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
        ini_set('display_errors', FALSE);
      }
    }
    
    /**
     * Setter function for debug directive
     * 
     * PHP Error reporting level is also set based on this value.
     * 
     * @param bool $val Debug flag
     * 
     * @return void
     */
    protected function setHttpSupport($val)
    {
      $val = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
      $this->params['httpSupport'] = (bool) $val;
    }
    
    /**
     * Setter method for object's $timezone directive
     * 
     * If no value is specified the function defaults to GMT.
     * 
     * **IMPORTANT:** This setter cannot override the `date.timezone` setting
     * if it exists in php.ini
     * 
     * @param string $tz PHP timezone abbreviation
     * 
     * @return Object instance for method chaining
     * @throws exceptions\InvalidArgumentException On invalid timezone
     */
    protected function setTimezone($tz)
    {
      $tz = NULL === $tz ? 'GMT' : $tz;
      try {
        date_default_timezone_set($tz);
        $this->params['timezone'] = $tz;
      } catch (exceptions\ErrorException $e) {
        // detect error message resulting from invalid timezone
        if (stristr($e->getMessage(), 'is invalid in')) {
          $msg = "Invalid timezone identifier ($tz) specified";
          throw new exceptions\InvalidArgumentException($msg);
        }
      }
    }
    
    /**
     * Setter method for custom_404_handler directive
     * 
     * @param callback $val Callback function
     * 
     * @return void
     */
    protected function setCustom404Handler($val=NULL)
    {
      if (NULL !== $val) {
        $val = is_string($val) ? self::dotNamespaceTrans($val) : $val;
        if (is_callable($val)) {
          $this->params['custom404Handler'] = $val;
        } else {
          $msg = 'Invalid 404 handler: handler must be callable';
          throw new exceptions\InvalidArgumentException($msg);
        }
      }
    }
    
    /**
     * Setter function for custom_500_handler directive
     * 
     * @param callback $val Callback function
     * 
     * @return void
     */
    protected function setCustom500Handler($val=NULL)
    {
      if (NULL !== $val) {
        $val = is_string($val) ? self::dotNamespaceTrans($val) : $val;
        if (is_callable($val)) {
          $this->params['custom500Handler'] = $val;
        } else {
          $msg = 'Invalid 500 handler: handler must be callable';
          throw new exceptions\InvalidArgumentException($msg);
        }
      }
    }
  }
}
