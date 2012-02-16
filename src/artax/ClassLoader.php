<?php

/**
 * Artax ClassLoader
 * 
 * Class is based heavily on the original SplClassLoader specified by the PSR-0
 * standard found here:
 * 
 * http://groups.google.com/group/php-standards/web/final-proposal
 * 
 * 
 */

namespace artax {

  class ClassLoader
  {
    protected $ext = '.php';
    protected $namespace;
    protected $includePath;
    protected $nsSeparator = '\\';
    
    /**
     * Creates a new <tt>SplClassLoader</tt> that loads classes of the
     * specified namespace.
     * 
     * @param string $ns The namespace to use.
     */
    public function __construct($ns = NULL, $includePath = NULL)
    {
      $this->namespace   = $ns;
      $this->includePath = $includePath;
    }
    
    /**
     * Sets the namespace separator used by classes using this class loader.
     * 
     * @param string $sep The separator to use.
     */
    public function setNsSeparator($sep)
    {
      $this->nsSeparator = $sep;
    }
    
    /**
     * Gets the namespace seperator used by classes using this class loader.
     *
     * @return void
     */
    public function getNsSeparator()
    {
      return $this->nsSeparator;
    }
    
    /**
     * Sets the base include path for all class files using this class loader.
     * 
     * @param string $includePath
     */
    public function setIncludePath($includePath)
    {
      $this->includePath = $includePath;
    }
    
    /**
     * Gets the base include path for all class files using this class loader.
     *
     * @return string $includePath
     */
    public function getIncludePath()
    {
      return $this->includePath;
    }
    
    /**
     * Sets the file extension of class files using this class loader.
     * 
     * @param string $ext
     */
    public function setExt($ext)
    {
      $this->ext = $ext;
    }
    
    /**
     * Gets the file extension of class files using this class loader.
     *
     * @return string $ext
     */
    public function getExt()
    {
      return $this->ext;
    }
    
    /**
     * Installs this class loader on the SPL autoload stack.
     * 
     * @return void
     */
    public function register()
    {
      spl_autoload_register(array($this, 'loadClass'));
    }
    
    /**
     * Uninstalls this class loader from the SPL autoloader stack.
     * 
     * @return void
     */
    public function unregister()
    {
      spl_autoload_unregister(array($this, 'loadClass'));
    }
    
    /**
     * Loads the given class or interface.
     * 
     * @param string $className The name of the class to load.
     * @return void
     */
    public function loadClass($className)
    {
      if (null === $this->namespace || $this->namespace.$this->nsSeparator === substr($className, 0, strlen($this->namespace.$this->nsSeparator))) {
        $fileName = '';
        $namespace = '';
        if (false !== ($lastNsPos = strripos($className, $this->nsSeparator))) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName = str_replace($this->nsSeparator, DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }
        $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . $this->ext;
        require ($this->includePath !== null ? $this->includePath . DIRECTORY_SEPARATOR : '') . $fileName;
      }
    }
  }
}

