<?php

/**
 * Artax FileCacheDriver Class File
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
   * FileCacheDriver Class
   * 
   * @category   artax
   * @package    blocks
   * @subpackage cache
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class FileCacheDriver implements CacheDriverInterface
  {
    /**
     * @var string
     */
    protected $cacheDir;
    
    /**
     * @var int
     */ 
    protected $cacheTtl;
    
    /**
     * Constructor
     * 
     * @param string $cacheDir A writable directory in which to store cache files
     * @param int    $cacheTtl The number of seconds a cache file can exist before
     *                         it is considered stale
     * 
     * @return void
     * @throws exceptions\InvalidArgumentException On non-writable cache directory
     */
    public function __construct($cacheDir=NULL, $cacheTtl=3600)
    {
      if (NULL !== $cacheDir) {
        $this->cacheDir = $this->setCacheDir($cacheDir);
      }
      if (NULL !== $cacheTtl) {
        $this->cacheTtl = $this->setCacheTtl($cacheTtl);
      }
    }
    
    /**
     * Store data in the cache, overwriting existing data even if not expired
     * 
     * @param string $id         Cached entity identifier
     * @param mixed  $data       Specified cache data
     * 
     * @return mixed Returns the number of bytes written, or **FALSE** on error
     *               or failure to obtain a file lock.
     * @throws exceptions\UnexpectedValueException If cache directory not specified
     */
    public function store($id, $data)
    {
      $this->validateCacheDir();
      
      $fp = fopen($this->cacheDir . "/$id", 'r+');
      
      if (flock($fp, LOCK_EX)) { // do an exclusive write lock
        ftruncate($fp, 0);
        $data = serialize($data);
        $bytes = fwrite($fp, $data);
        flock($fp, LOCK_UN); // release the lock
        fclose($fp);
        return $bytes;
      } else {
        return FALSE;
      }
    }
    
    /**
     * Store data if identifier doesn't exist or associated data has expired
     * 
     * @param string $id         Cached entity identifier
     * @param mixed  $data       Specified cache data
     * @param int    $ttl        Time To Live
     * 
     * @return bool Returns **TRUE** on success or **FALSE** on failure.
     * @throws exceptions\UnexpectedValueException If cache directory not specified
     */
    public function add($id, $data, $ttl=NULL)
    {
      if ( ! $this->exists($id, $ttl)) {
        return $this->store($id, $data);
      } else {
        return FALSE;
      }
    }
    
    /**
     * Remove data from the cache
     * 
     * @param string $id         Cached entity identifier
     * 
     * @return bool Returns **TRUE** on success or **FALSE** on failure.
     * @throws exceptions\UnexpectedValueException If cache directory not specified
     */
    public function delete($id)
    {
      $this->validateCacheDir();
      $file = $this->cacheDir . "/$id";
      return unlink($file);
    }
    
    /**
     * Determine if the specified identifier exists in the cache
     * 
     * @param string $id         Cached entity identifier
     * 
     * @return bool Returns **TRUE** on success or **FALSE** on failure.
     * @throws exceptions\UnexpectedValueException If cache directory not specified
     */
    public function exists($id, $ttl=NULL)
    {
      $this->validateCacheDir();
      $file = $this->cacheDir . "/$id";
      $ttl = NULL === $ttl ? $this->cacheTtl : (int) $ttl;
      $minTime = time() - $ttl;
      
      if (file_exists($file) && ($ftime = filemtime($file)) >= $minTime) {
        return TRUE;
      } elseif ($ftime < $minTime) {
        $this->delete($id);
        return FALSE;
      } else {
        return FALSE;
      }
    }
    
    /**
     * Retrieve data from the cache if available
     * 
     * @param string $id         Cached entity identifier
     * 
     * @return mixed The stored data on success; **FALSE** on failure.
     * @throws exceptions\UnexpectedValueException If cache directory not specified
     */
    public function fetch($id)
    {
      if ( ! $this->exists($id)) {
        return FALSE;
      }
      
      $fp = fopen($this->cacheDir . "/$id", 'r');
      
      if (flock($fp, LOCK_SH)) { // do a shared reading lock
        $data = fread($fp, filesize($fp));
        flock($fp, LOCK_UN); // release the lock
        fclose($fp);
        return unserialize($data);
      } else {
        return FALSE;
      }
    }
    
    /**
     * Clears all user data from the cache
     * 
     * @return bool Returns **TRUE** on success or **FALSE** on failure.
     * @throws exceptions\UnexpectedValueException If cache directory not specified
     */
    public function clear()
    {
      $this->validateCacheDir();
      foreach (glob("$this->cacheDir/*.*") as $file) {
        unlink($file);
      }
    }
    
    /**
     * Setter method for $cacheDir property
     * 
     * @param string $cacheDir A writable directory in which to store cache files
     * 
     * @return FileCacheDriver Object instance for method chaining
     * @throws exceptions\InvalidArgumentException On non-writable cache directory
     */
    public function setCacheDir($cacheDir)
    {
      $cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
      if (is_dir($cacheDir) && is_writable($cacheDir)) {
        $this->cacheDir = $cacheDir;
        return $this;
      } else {
        $msg = "Invalid file cache directory specified: $cacheDir is not a " .
          'writable directory';
        throw new exceptions\InvalidArgumentException($msg);
      }
    }
    
    /**
     * Getter method for $cacheDir property
     * 
     * @return string Returns the directory path where files are cached
     */
    public function getCacheDir()
    {
      return $this->cacheDir;
    }
    
    /**
     * Setter method for $cacheTtl property
     * 
     * @param int $cacheTtl The number of seconds before a cached file becomes stale
     * 
     * @return FileCacheDriver Object instance for method chaining
     */
    public function setCacheTtl($cacheTtl)
    {
      $this->cacheTtl = (int)$cacheTtl;
      return $this;
    }
    
    /**
     * Getter method for $cacheTtl property
     * 
     * @return string Returns the number of seconds a cached file is fresh
     */
    public function getCacheTtl()
    {
      return $this->cacheTtl;
    }
    
    /**
     * Validate that the object $cacheDir property is set
     * 
     * @return void
     * @throws exceptions\UnexpectedValueException If cache directory not set
     */
    protected function validateCacheDir()
    {
      if ( ! $this->cacheDir) {
        $msg = 'Cannot use file cache: no cache directory specified';
        throw new exceptions\UnexpectedValueException($msg);
      }
    }
  }
}
