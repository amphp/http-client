<?php

/**
 * Artax ConfigLoader Class File
 * 
 * PHP version 5.4
 * 
 * @category Artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax {

  /**
   * Artax ConfigLoader class
   * 
   * @category Artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   * @todo     Add loader methods for YAML/JSON/XML
   */
  class ConfigLoader
  {
    /**
     * Configuration file path
     * @var string
     */
    protected $configFile;
    
    /**
     * Configuration settings array
     * @var array
     */
    protected $configArr;
    
    /**
     * Setter method for config file property
     * 
     * @param string $configFile Path to app configuration file
     * 
     * @return ConfigLoader Returns object instance for method chaining.
     */
    public function setConfigFile($configFile)
    {
      $this->configFile = $configFile;
      return $this;
    }
    
    /**
     * Load specified configuration file
     * 
     * @return Object instance for method chaining
     * @throws \UnexpectedValueException On unreadable config file
     */
    public function load()
    {
      $configFile = $this->configFile;
      
      $fileInfo = new \Finfo(FILEINFO_MIME_TYPE);      
      try {
        $type = $fileInfo->file($configFile);
      } catch (\ErrorException $e) {
        $configFile !== NULL ? $configFile : 'NULL';
        $msg = "Config file could not be read: $configFile";
        throw new \UnexpectedValueException($msg);
      }
      
      switch ($type) {
      case 'text/x-php':
        $cfg = $this->loadNative($configFile);
        break;
      default:
        $msg = "Invalid config file type: $type";
        throw new \UnexpectedValueException($msg);
      }
      $this->configArr = $cfg;
      
      return $this;
    }
    
    /**
     * Load configuration directly from a PHP config file
     * 
     * @param string $configFile The filepath to the config file
     * 
     * @return array Returns the `$cfg` array from the specified config file. If
     *               `$cfg` is invalid or nonexistent an empty array is returned.
     */
    protected function loadNative($configFile)
    {
      require $configFile;
      return $cfg;
    }
    
    /**
     * Getter method for protected `$configArr` property
     * 
     * @return array Returns an array of loaded configuration values
     */
    public function getConfigArr()
    {
      return $this->configArr;
    }
  }
}
