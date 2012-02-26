<?php

/**
 * Artax ConfigLoader Class File
 * 
 * PHP version 5.3
 * 
 * @category Artax
 * @package  Core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {

  /**
   * Artax ConfigLoader class
   * 
   * @category Artax
   * @package  Core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
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
     * Constructor
     * 
     * Currently the only file type supported is php. The `$type` parameter is
     * included with the intention of adding support for other type parsers for
     * JSON, XML, YAML, etc. in the future.
     * 
     * @param string $configFile Path to app configuration file
     * 
     * @return void
     * @todo Add loader methods for YAML/JSON/XML
     */
    public function __construct($configFile=NULL) {
      if (NULL !== $configFile) {
        $this->configFile = $configFile;
      }
    }
    
    /**
     * Load specified configuration file
     * 
     * @return Object instance for method chaining
     * @throws exceptions\ConfigException On unreadable config file
     */
    public function load()
    {
      $configFile = $this->configFile;
      /*
      $cachedArr = $this->loadFromCache($configFile);
      if (FALSE !== $cachedArr) {
        $this->configArr = $cachedArr;
        return $this;
      }      
      */
      $fileInfo = new \Finfo(FILEINFO_MIME_TYPE);      
      try {
        $type = $fileInfo->file($configFile);
      } catch (exceptions\ErrorException $e) {
        $msg = "Config file could not be read: $configFile";
        throw new exceptions\UnexpectedValueException($msg);
      }
      
      switch ($type) {
        case 'text/x-php':
          $cfg = $this->loadPhpConfig($configFile);
          break;
        default:
          $msg = "Invalid config file type: $type";
          throw new exceptions\UnexpectedValueException($msg);
      }
      
      //$this->storeInCache($configFile, $cfg);
      $this->configArr = $cfg;
      
      return $this;
    }
    
    /**
     * Load configuration directly from a PHP config file
     * 
     * @return array $cfg Returns the `$cfg` array from the specified config
     *                    file. If `$cfg` is invalid or nonexistent an empty
     *                    array is returned.
     */
    protected function loadPhpConfig($configFile)
    {
      require $configFile;
      return $cfg;
    }
    
    /**
     * 
     */
    public function setConfigFile($configFile)
    {
      $this->configFile = $configFile;
      return $this;
    }
    
    /**
     * Getter method for protected `$configArr` property
     * 
     * @return array Array of config directives
     */
    public function getConfigArr()
    {
      return $this->configArr;
    }
  }
}
