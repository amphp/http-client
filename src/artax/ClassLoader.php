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
 * @author   Jonathan H. Wage <jonwage@gmail.com>
 * @author   Roman S. Borschel <roman@code-factory.org>
 * @author   Matthew Weier O'Phinney <matthew@zend.com>
 * @author   Kris Wallsmith <kris.wallsmith@gmail.com>
 * @author   Fabien Potencier <fabien.potencier@symfony-project.org>
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
   * @author   Jonathan H. Wage <jonwage@gmail.com>
   * @author   Roman S. Borschel <roman@code-factory.org>
   * @author   Matthew Weier O'Phinney <matthew@zend.com>
   * @author   Kris Wallsmith <kris.wallsmith@gmail.com>
   * @author   Fabien Potencier <fabien.potencier@symfony-project.org>
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class ClassLoader extends ClassLoaderAbstract
  {
    /**
     * Loads the given class, trait or interface
     * 
     * @param string $className The name of the class to load.
     * @return void
     */
    public function loadClass($className)
    {
      $nsMatch = $this->namespace . $this->nsSeparator;
      
      if (NULL === $this->namespace
        || $nsMatch === substr($className, 0, strlen($nsMatch))) {
        
        $ds        = DIRECTORY_SEPARATOR;
        $fileName  = '';
        $namespace = '';
        
        if (FALSE !== ($lastNsPos = strripos($className, $this->nsSeparator))) {
          $namespace = substr($className, 0, $lastNsPos);
          $className = substr($className, $lastNsPos + 1);
          $fileName  = str_replace($this->nsSeparator, $ds, $namespace) . $ds;
        }
        $fileName .= str_replace('_', $ds, $className) . $this->ext;
        $path = NULL !== $this->includePath ? $this->includePath . $ds : '';
        
        require $path . $fileName;
      }
    }
  }
}

