<?php

/**
 * Artax CacheableInterface Interface File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    blocks
 * @subpackage cache
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\blocks\cache {
  
  /**
   * CacheableInterface Interface
   * 
   * @category   artax
   * @package    blocks
   * @subpackage cache
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface CacheableInterface
  {
    /**
     * Load an object from the cache
     */
    public function loadFromCache();
    
    /**
     * Store an object in the cache
     */
    public function storeInCache();
  }
}
