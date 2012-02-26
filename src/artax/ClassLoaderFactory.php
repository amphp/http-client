<?php

/**
 * Artax ClassLoaderFactory Class File
 * 
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {
  
  /**
   * ClassLoaderFactory
   * 
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class ClassLoaderFactory
  {
    /**
     * Builds a ClassLoader of the specified type
     * 
     * @param string $type The type of ClassLoader to instantiate
     * @param string $ns   The namespace this loader is relevant for
     */
    public function make($type, $ns)
    {
      switch ($type) {
        case 'standard':
          return new ClassLoader($ns);
        default:
          return new ClassLoader($ns);
      }
    }
  }
}
