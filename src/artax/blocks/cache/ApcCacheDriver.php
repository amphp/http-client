<?php

/**
 * Artax ApcCacheDriver Class File
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
   * ApcCacheDriver Class
   * 
   * @category   artax
   * @package    blocks
   * @subpackage cache
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class ApcCacheDriver implements CacheDriverInterface
  {
    /**
     * Store data in the cache, overwriting existing data even if not expired
     * 
     * @param string $id         Cached entity identifier
     * @param mixed  $data       Specified cache data
     * @param int    $ttl        Time To Live
     * 
     * @return bool Returns **TRUE** on success or **FALSE** on failure.
     */
    public function store($id, $data, $ttl=0)
    {
      return apc_store($id, $data, $ttl);
    }
    
    /**
     * Store data if identifier doesn't exist or associated data has expired
     * 
     * @param string $id         Cached entity identifier
     * @param mixed  $data       Specified cache data
     * @param int    $ttl        Time To Live
     * 
     * @return bool Returns **TRUE** on success or **FALSE** on failure.
     */
    public function add($id, $data, $ttl=0)
    {
      return apc_add($id, $data, $ttl);
    }
    
    /**
     * Remove data from the cache
     * 
     * @param string $id         Cached entity identifier
     * 
     * @return bool Returns **TRUE** on success or **FALSE** on failure.
     */
    public function delete($id)
    {
      return apc_delete($id);
    }
    
    /**
     * Determine if the specified identifier exists in the cache
     * 
     * @param string $id         Cached entity identifier
     * 
     * @return bool Returns **TRUE** on success or **FALSE** on failure.
     */
    public function exists($id)
    {
      return apc_exists($id);
    }
    
    /**
     * Retrieve data from the cache if available
     * 
     * @param string $id         Cached entity identifier
     * 
     * @return mixed The stored data on success; **FALSE** on failure.
     */
    public function fetch($id)
    {
      return apc_fetch($id);
    }
    
    /**
     * Clears all user data from the cache
     * 
     * @return bool Returns **TRUE** on success or **FALSE** on failure.
     */
    public function clear()
    {
      return apc_clear_cache('user');
    }
  }
}
