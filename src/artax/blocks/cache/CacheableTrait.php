<?php

/**
 * Artax CacheableTrait Trait File
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
   * Artax CacheableTrait Trait
   * 
   * @category   artax
   * @package    blocks
   * @subpackage cache
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  trait CacheableTrait
  {
    /**
     * Distinguishes stored objects of the same class in the cache
     * @var string
     */
    protected $cachePrefix = '';
    
    /**
     * Caching mechanism driver
     * @var DriverInterface
     */
    protected $cacheDriver;
    
    /**
     * Generated cache identifier hash
     * @var string
     */
    protected $cacheHash;
    
    /**
     * Retrieve object from the cache if available
     * 
     * @return mixed Returns NULL if no entity matched in cache or if no cache
     *               driver dependency exists
     */
    protected function loadFromCache()
    {
      if ( ! $this->cacheDriver) {
        return NULL;
      } else {
        $hash = $this->cacheHash ?: $this->cacheHash();
        if ($this->cacheDriver->exists($hash)) {
          return $this->cacheDriver->fetch($hash);
        }
      }
      return NULL;
    }
    
    /**
     * Store a specified config file's parsed data in cache
     * 
     * @return bool Returns TRUE if object successfully cached or FALSE If not
     */
    protected function storeInCache()
    {
      if ( ! $this->cacheDriver) {
        return FALSE;
      } else {
        $hash = $this->cacheHash ?: $this->cacheHash();
        $this->cacheDriver->store($hash, $this);
        return TRUE;
      }
    }
    
    /**
     * Hash entity name for cache storage lookups
     * 
     * @return string Returns a `sha1` hash of the cached entity identifier
     */
    protected function cacheHash()
    {
      $hash = sha1($this->cachePrefix . __CLASS__);
      $this->cacheHash = $hash;
      return $hash;
    }
  }
}
