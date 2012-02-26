<?php

/**
 * Artax ClassLoader Interface
 * 
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {
  
  /**
   * ClassLoader Interface
   * 
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface ClassLoaderInterface
  {
    /**
     * Loads the given class, trait or interface
     * 
     * @param string $className The name of the class to load.
     */
    public function loadClass($className);
  }
}
