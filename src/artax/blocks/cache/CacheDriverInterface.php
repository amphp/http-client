<?php

/**
 * Artax CacheDriverInterface File
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
   * CacheDriverInterface
   * 
   * @category   artax
   * @package    blocks
   * @subpackage cache
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface CacheDriverInterface
  {
    /**
     * Store data in the cache, overwriting existing data even if not expired
     * 
     * @param string $id         Cached entity identifier
     * @param mixed  $data       Specified cache data
     */
    public function store($id, $data);
    
    /**
     * Store data if identifier doesn't exist or associated data has expired
     * 
     * @param string $id         Cached entity identifier
     * @param mixed  $data       Specified cache data
     */
    public function add($id, $data);
    
    /**
     * Remove data from the cache
     * 
     * @param string $id         Cached entity identifier
     */
    public function delete($id);
    
    /**
     * Determine if the specified identifier exists in the cache
     * 
     * @param string $id         Cached entity identifier
     */
    public function exists($id);
    
    /**
     * Retrieve data from the cache if available
     * 
     * @param string $id         Cached entity identifier
     */
    public function fetch($id);
    
    /**
     * Clears all data from the cache
     */
    public function clear();
  }
}
