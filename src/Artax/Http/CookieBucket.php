<?php

/**
 * Artax HTTP Package CookieBucket Class File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    blocks
 * @subpackage http
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Http {

  /**
   * HTTP CookieBucket Class
   * 
   * The class _DOES NOT_ perform any filtering or processing on the passed 
   * params; it simply stores passed values in a key=>value container. It is
   * **exceedingly** important that you verify your own input.
   * 
   * @category   Artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class CookieBucket extends BucketAbstract
  {
    /**
     * Auto-detect HTTP request parameters and populate associated bucket params
     * 
     * Only key=>value pairs passed via HTTP GET and HTTP POST are detected.
     * COOKIE values are ignored as they are stored in a separate bucket.
     * 
     * @param array $_cookie A COOKIE parameter array
     * 
     * @return Object instance for method chaining
     */
    public function detect($_cookie=NULL) {
      $this->clear();
      $_cookie = $_cookie ? $_cookie : $_COOKIE;
      if ($_cookie) {
        $this->load($_cookie);
      }
      return $this;
    }
  }
}
