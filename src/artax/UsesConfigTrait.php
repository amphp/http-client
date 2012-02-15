<?php

/**
 * Artax UsesConfigTrait Trait File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {

  /**
   * Artax UsesConfigTrait Trait
   * 
   * @category   artax
   * @package    core
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  trait UsesConfigTrait
  {
    /**
     * Config object instance
     * @var ConfigInterface
     */
    protected $config;
    
    /**
     * Setter method for protected `$config` property
     * 
     * @param Config $config Configuration object instance
     * 
     * @return mixed Object instance for method chaining
     */
    public function setConfig(Config $config)
    {
      $this->config = $config;
      return $this;
    }
    
    /**
     * Getter method for protected `$config` property
     * 
     * @return Config Returns configuration object or `NULL` if not set
     */
    public function getConfig()
    {
      return $this->config;
    }
  }
}
