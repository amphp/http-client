<?php

/**
 * Artax ClassLoader
 * 
 * Class is based very heavily on the original SplClassLoader specified by the
 * PSR-0 standard example found here:
 * 
 * http://groups.google.com/group/php-standards/web/final-proposal
 * 
 * The only differences in this iteration are:
 *  - Aesthetic code formatting changes
 *  - Variable name changes
 *  - Class properties that used to be private are now protected to allow
 * subclassing for cache support
 * 
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {
  
  /**
   * Allows PSR-0 compliant registration of namespaced class autoloading
   * 
   * The class is essentially the same as the original specification here:
   * http://groups.google.com/group/php-standards/web/final-proposal
   * 
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  abstract class ClassLoaderAbstract implements ClassLoaderInterface
  {
    protected $ext = '.php';
    protected $namespace;
    protected $includePath;
    protected $nsSeparator = '\\';
    
    /**
     * Creates a new <tt>SplClassLoader</tt> that loads classes of the
     * specified namespace.
     * 
     * @param string $ns          The namespace to use
     * @param string $includePath The path to use when searching for this
     *                            namespace's class files
     */
    public function __construct($ns = NULL, $includePath = NULL)
    {
      $this->namespace   = $ns;
      $this->includePath = $includePath;
    }
    
    /**
     * 
     */
    public function getNamespace()
    {
      return $this->namespace;
    }
    
    /**
     * Sets the namespace separator used by classes using this class loader.
     * 
     * @param string $sep The separator to use.
     */
    public function setNsSeparator($sep)
    {
      $this->nsSeparator = $sep;
      return $this;
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
      return $this;
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
      return $this;
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
      return spl_autoload_register([$this, 'loadClass']);
    }
    
    /**
     * Uninstalls this class loader from the SPL autoloader stack.
     * 
     * @return void
     */
    public function unregister()
    {
      return spl_autoload_unregister([$this, 'loadClass']);
    }
  }
}

